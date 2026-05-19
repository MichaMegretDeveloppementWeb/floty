<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Data\User\Invoice\BulkInvoiceGenerationFailedItemData;
use App\Data\User\Invoice\BulkInvoiceGenerationGeneratedItemData;
use App\Data\User\Invoice\BulkInvoiceGenerationReportData;
use App\Enums\Invoice\BulkInvoiceGenerationFailureReason;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Invoice\InvoiceAlreadyExistsException;
use App\Services\Billing\PendingInvoicesResolver;
use DomainException;
use Throwable;

/**
 * Generates every pending monthly invoice for a `(company, year)`
 * couple in one batch; symmetric of the per-month
 * {@see GenerateInvoiceAction} applied over
 * `PendingInvoicesResolver::pendingMonthsForCompanyYear()`.
 *
 * Execution doctrine:
 *
 *   1. Strict sequential ordering (Jan → Dec) so the
 *      `invoice_number` sequence stays consistent with the
 *      chronological order.
 *
 *   2. One transaction per month, carried by each
 *      `GenerateInvoiceAction::execute()` call. No enclosing
 *      transaction, so a single-month failure does not invalidate
 *      previously issued invoices.
 *
 *   3. Best-effort: a month-level exception is captured into
 *      `failed[]`, the loop continues with the next month, and the
 *      final report aggregates `generated[]` / `failed[]`.
 *
 *   4. Race-safe numbering: the `lockForUpdate()` posted by
 *      `InvoiceReadRepository::maxSequenceForYearMonth()` serialises
 *      concurrent generation on the same `(year, month)`.
 *
 *   5. Long-running guard: `set_time_limit(0)` is raised at entry
 *      because rendering N PDFs (~0.5-2 s each) may exceed the
 *      default PHP timeout on a full year. Floty V1 has no queue, so
 *      the request stays synchronous with the UI loader/disabled
 *      button.
 *
 * @phpstan-import-type IssuerPayload from GenerateInvoiceAction
 */
final readonly class BulkGenerateInvoicesAction
{
    public function __construct(
        private PendingInvoicesResolver $pendingResolver,
        private GenerateInvoiceAction $generateAction,
    ) {}

    /**
     * @param  IssuerPayload  $issuer
     */
    public function execute(
        int $companyId,
        int $year,
        int $generatedByUserId,
        array $issuer,
    ): BulkInvoiceGenerationReportData {
        // Rendering up to 12 PDFs may exceed the default PHP timeout.
        @set_time_limit(0);

        $months = $this->pendingResolver->pendingMonthsForCompanyYear($companyId, $year);

        /** @var list<BulkInvoiceGenerationGeneratedItemData> $generated */
        $generated = [];
        /** @var list<BulkInvoiceGenerationFailedItemData> $failed */
        $failed = [];

        foreach ($months as $month) {
            try {
                $invoice = $this->generateAction->execute(
                    companyId: $companyId,
                    year: $year,
                    month: $month,
                    generatedByUserId: $generatedByUserId,
                    issuer: $issuer,
                );

                $generated[] = new BulkInvoiceGenerationGeneratedItemData(
                    month: $month,
                    invoiceId: $invoice->id,
                    invoiceNumber: $invoice->invoice_number,
                );
            } catch (InvoiceAlreadyExistsException $e) {
                // Race condition: a concurrent generation persisted an
                // invoice for this month between the pending resolution
                // and the call. Report and continue.
                $failed[] = new BulkInvoiceGenerationFailedItemData(
                    month: $month,
                    reason: BulkInvoiceGenerationFailureReason::AlreadyExists,
                    errorMessage: $e->getMessage(),
                );
            } catch (MissingPricingException $e) {
                $failed[] = new BulkInvoiceGenerationFailedItemData(
                    month: $month,
                    reason: BulkInvoiceGenerationFailureReason::MissingPricing,
                    errorMessage: $e->getMessage(),
                );
            } catch (DomainException $e) {
                $failed[] = new BulkInvoiceGenerationFailedItemData(
                    month: $month,
                    reason: BulkInvoiceGenerationFailureReason::NotPastMonth,
                    errorMessage: $e->getMessage(),
                );
            } catch (Throwable $e) {
                // Other failure (PDF, persistence, ...). Orphan PDF
                // cleanup is already handled inside the per-month
                // transaction of `GenerateInvoiceAction`.
                $failed[] = new BulkInvoiceGenerationFailedItemData(
                    month: $month,
                    reason: BulkInvoiceGenerationFailureReason::Unexpected,
                    errorMessage: $e->getMessage(),
                );
            }
        }

        return new BulkInvoiceGenerationReportData(
            companyId: $companyId,
            year: $year,
            generated: $generated,
            failed: $failed,
            skipped: [],
        );
    }
}
