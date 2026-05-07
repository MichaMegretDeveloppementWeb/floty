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
use App\Services\Invoice\InvoicePdfRenderer;
use App\Services\Invoice\InvoicePdfStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Génère une facture mensuelle complète pour `(company × year × month)`
 * (Phase 14.E V1.2).
 *
 * Pipeline en transaction (atomicité — si l'une des étapes échoue,
 * rollback complet, aucune corruption d'état) :
 *
 *   1. Vérifie l'unicité applicative (`InvoiceAlreadyExistsException`
 *      si une facture existe déjà pour ce couple)
 *   2. Calcule la facture via {@see BillingCalculator::calculate()}
 *      (peut lever `MissingPricingException` qui remonte non-attrapée
 *      à l'appelant — l'utilisateur verra la liste des tarifs manquants
 *      à renseigner)
 *   3. Pré-attribue le `invoice_number` séquentiel
 *   4. Rend le PDF via {@see InvoicePdfRenderer}
 *   5. Persiste le PDF + calcule le hash via {@see InvoicePdfStorage}
 *   6. Persiste la facture + ses lignes (Models)
 *   7. Retourne l'`Invoice` créée (avec `lines` chargées)
 *
 * **Émetteur (Phase 14.G)** : pour V1.2 minimum, les métadonnées
 * émetteur sont passées en paramètre. Le contrôleur appelant est
 * responsable de les fournir (sera lu depuis `BillingSettings` en 14.G).
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
    ) {}

    /**
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     */
    public function execute(
        int $companyId,
        int $year,
        int $month,
        int $generatedByUserId,
        array $issuer,
    ): Invoice {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            throw (new ModelNotFoundException)
                ->setModel(Company::class, [$companyId]);
        }

        // Étape 1 : unicité applicative (avant le calcul coûteux).
        if ($this->invoiceReader->findForCompanyYearMonth($companyId, $year, $month) !== null) {
            throw InvoiceAlreadyExistsException::forCompanyYearMonth($companyId, $year, $month);
        }

        // Étape 2 : calcul (laisse remonter MissingPricingException).
        $calculation = $this->calculator->calculate($companyId, $year, $month);

        // Étape 3-7 en transaction.
        return DB::transaction(function () use (
            $companyId,
            $year,
            $month,
            $generatedByUserId,
            $issuer,
            $company,
            $calculation,
        ): Invoice {
            // Étape 3 : `invoice_number` séquentiel = `YYYY-MM-NNNN`.
            $sequence = $this->invoiceReader->maxSequenceForYearMonth($year, $month) + 1;
            $invoiceNumber = sprintf('%04d-%02d-%04d', $year, $month, $sequence);

            $generatedAt = Carbon::now();

            // Étape 4 : rendu PDF.
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

            // Étape 5 : persiste le PDF + calcule le hash.
            $storage = $this->pdfStorage->store(
                $year,
                $companyId,
                $invoiceNumber,
                $pdfBinary,
            );

            try {
                // Étape 6 : persiste la facture.
                $invoice = $this->invoiceWriter->persist([
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                    'invoice_number' => $invoiceNumber,
                    'total_ht_cents' => $calculation->totalCents,
                    'pdf_path' => $storage['path'],
                    'pdf_hash' => $storage['hash'],
                    'generated_at' => $generatedAt,
                    'generated_by_user_id' => $generatedByUserId,
                ]);

                // Étape 7 : persiste les lignes.
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
                    ],
                    $calculation->lines,
                );
                $this->invoiceWriter->persistLines($invoice->id, $linesAttributes);

                return $invoice->fresh(['lines'])
                    ?? throw new \RuntimeException('Failed to reload invoice after persist.');
            } catch (\Throwable $e) {
                // Cleanup orphan PDF (chantier T4 / Phase 14.P) : si l'INSERT
                // DB échoue après le `Storage::put`, la transaction rollback
                // mais le fichier reste sur disque. On le supprime avant
                // re-throw pour ne pas bloquer un éventuel retry (le storage
                // refuserait l'écrasement) ni laisser d'orphan file.
                $this->pdfStorage->delete($storage['path']);
                throw $e;
            }
        });
    }
}
