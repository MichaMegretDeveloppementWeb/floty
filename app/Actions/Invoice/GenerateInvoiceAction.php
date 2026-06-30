<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Data\User\Billing\BillingLineData;
use App\Exceptions\Invoice\InvoiceAlreadyExistsException;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\Discount\DiscountApplier;
use App\Services\Billing\Discount\DiscountResolver;
use App\Services\Invoice\InvoicePdfRenderer;
use App\Services\Invoice\InvoicePdfStorage;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates the full monthly invoice for a `(company × year × month)`
 * triple.
 *
 * Pipeline in transaction (atomic; any step failure rolls back the
 * whole invoice, preventing state corruption):
 *   1. Uniqueness guard (raises `InvoiceAlreadyExistsException`).
 *   2. Calculation via {@see BillingCalculator::calculate()} (may
 *      raise `MissingPricingException`, propagated to the caller).
 *   3. Pre-allocate a sequential `invoice_number`.
 *   4. Render the PDF via {@see InvoicePdfRenderer}.
 *   5. Persist the PDF + compute the SHA-256 hash via
 *      {@see InvoicePdfStorage}.
 *   6. Persist the invoice + its lines.
 *   7. Return the created `Invoice` with `lines` eager-loaded.
 *
 * Issuer metadata is passed as a parameter; the caller is responsible
 * for sourcing it (typically from `BillingSettings`).
 *
 * @phpstan-type IssuerPayload array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}
 */
final readonly class GenerateInvoiceAction
{
    public function __construct(
        private CompanyReadRepositoryInterface $companies,
        private InvoiceReadRepositoryInterface $invoiceReader,
        private InvoiceWriteRepositoryInterface $invoiceWriter,
        private BillingCalculator $calculator,
        private InvoicePdfRenderer $pdfRenderer,
        private InvoicePdfStorage $pdfStorage,
        private DiscountResolver $discountResolver,
        private DiscountApplier $discountApplier,
    ) {}

    /**
     * @param  IssuerPayload  $issuer
     */
    public function execute(
        int $companyId,
        int $year,
        int $month,
        int $generatedByUserId,
        array $issuer,
    ): Invoice {
        // Defence-in-depth guard: an invoice may be generated for the
        // current month (provisional, regenerable later) or any elapsed
        // month, but never for a future month. Closes the door on direct
        // POST/scripts; the UI button enforces the same bound.
        $now = CarbonImmutable::now();
        if ($year > $now->year || ($year === $now->year && $month > $now->month)) {
            throw new DomainException(sprintf(
                'Une facture ne peut pas être générée pour un mois à venir (%04d-%02d).',
                $year,
                $month,
            ));
        }

        $company = $this->companies->findById($companyId);
        if ($company === null) {
            throw (new ModelNotFoundException)
                ->setModel(Company::class, [$companyId]);
        }

        // Applicative uniqueness check, before the costly calculation.
        if ($this->invoiceReader->findForCompanyYearMonth($companyId, $year, $month) !== null) {
            throw InvoiceAlreadyExistsException::forCompanyYearMonth($companyId, $year, $month);
        }

        // Calculation (lets `MissingPricingException` bubble up).
        $calculation = $this->calculator->calculate($companyId, $year, $month);

        // Apply rental discounts active over the period. The snapshot
        // is frozen in `gross_total_cents` / `discount_cents` /
        // `applied_discount_id` below. Strict equivalence branch when
        // no discount applies.
        $discountIndex = $this->discountResolver->preloadForCompanyYear($companyId, $year);
        $calculation = $this->discountApplier->applyWithIndex($calculation, $discountIndex);

        return DB::transaction(function () use (
            $companyId,
            $year,
            $month,
            $generatedByUserId,
            $issuer,
            $company,
            $calculation,
        ): Invoice {
            // Sequential `invoice_number` formatted `YYYY-MM-NNNN`.
            $sequence = $this->invoiceReader->maxSequenceForYearMonth($year, $month) + 1;
            $invoiceNumber = sprintf('%04d-%02d-%04d', $year, $month, $sequence);

            $generatedAt = Carbon::now();

            $companyMeta = [
                'legalName' => $company->legal_name,
                'siren' => $company->siren,
                'city' => $company->city,
            ];
            $pdfBinary = $this->pdfRenderer->render(
                $calculation,
                $issuer,
                $companyMeta,
                $invoiceNumber,
                $generatedAt,
            );

            $storage = $this->pdfStorage->store(
                $year,
                $companyId,
                $invoiceNumber,
                $pdfBinary,
            );

            try {
                $invoice = $this->invoiceWriter->persist([
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                    'invoice_number' => $invoiceNumber,
                    'total_ht_cents' => $calculation->totalCents,
                    'total_gross_cents' => $calculation->grossTotalCents,
                    'total_discount_cents' => $calculation->totalDiscountCents,
                    'pdf_path' => $storage['path'],
                    'pdf_hash' => $storage['hash'],
                    'generated_at' => $generatedAt,
                    'generated_by_user_id' => $generatedByUserId,
                ]);

                $linesAttributes = array_map(
                    static fn (BillingLineData $line): array => [
                        'vehicle_id' => $line->vehicleId,
                        'vehicle_label_snapshot' => sprintf(
                            '%s %s %s',
                            $line->licensePlate,
                            $line->brand,
                            $line->model,
                        ),
                        'days_used' => $line->daysUsed,
                        'months_billed' => $line->monthsBilled,
                        'weeks_billed' => $line->weeksBilled,
                        'days_billed' => $line->daysBilled,
                        'daily_rate_cents' => $line->dailyRateCents,
                        'weekly_rate_cents' => $line->weeklyRateCents,
                        'monthly_rate_cents' => $line->monthlyRateCents,
                        'total_ht_cents' => $line->totalCents,
                        'gross_total_cents' => $line->grossTotalCents,
                        'discount_cents' => $line->discountCents,
                        'applied_discount_id' => $line->appliedDiscountId,
                        // Snapshot label and basis points so historical
                        // display stays exact even if the discount is
                        // later renamed (immutability, ADR-0008).
                        'applied_discount_label_snapshot' => $line->appliedDiscountLabel,
                        'applied_discount_basis_points_snapshot' => $line->appliedDiscountBasisPoints,
                    ],
                    $calculation->lines,
                );
                $this->invoiceWriter->persistLines($invoice->id, $linesAttributes);

                return $invoice->fresh(['lines'])
                    ?? throw new \RuntimeException('Failed to reload invoice after persist.');
            } catch (\Throwable $e) {
                // Orphan PDF cleanup: if the DB INSERT fails after the
                // `Storage::put`, the transaction rolls back but the
                // file stays on disk. Delete it before re-throw to
                // unblock retries (storage would refuse to overwrite)
                // and avoid an orphan file.
                $this->pdfStorage->delete($storage['path']);
                throw $e;
            }
        });
    }
}
