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
 * Génère en masse toutes les annexes de facture en attente pour le couple
 * `(entreprise, année)` · symétrique du bouton « Générer » individuel
 * (cf. {@see GenerateInvoiceAction}) appliqué en série sur la liste
 * `PendingInvoicesResolver::pendingMonthsForCompanyYear()`.
 *
 * Doctrine d'exécution ·
 *
 *   1. **Séquentiel strict** · les mois sont traités dans l'ordre
 *      chronologique (Jan → Déc) · garantit une numérotation
 *      d'invoice_number cohérente avec l'ordre temporel des facturations.
 *
 *   2. **Une transaction par mois** · chaque appel `GenerateInvoiceAction`
 *      porte sa propre transaction (étapes 3-7 + cleanup PDF orphelin).
 *      Pas de transaction enveloppante · un échec sur un mois n'invalide
 *      pas les annexes déjà émises ni ne crée de PDF orphelin (le rollback
 *      du mois courant est complet).
 *
 *   3. **Best-effort** · une exception sur un mois est capturée et
 *      reportée dans `failed[]` ; la séquence continue avec le mois
 *      suivant. Le rapport final récapitule `generated[]` / `failed[]`.
 *
 *   4. **Numérotation race-safe** · héritée de `GenerateInvoiceAction` ·
 *      le `lockForUpdate()` posé par `InvoiceReadRepository::maxSequenceForYearMonth()`
 *      sérialise toute génération concurrente sur le même `(year, month)`
 *      (autre user, autre onglet, autre batch). Aucun conflit possible
 *      sur le numéro.
 *
 *   5. **Long-running défensif** · `set_time_limit(0)` est levé en début
 *      d'exécution · le rendu de N PDFs (~0.5-2 s chacun) peut dépasser
 *      le timeout PHP par défaut (30 s) sur une année complète chargée.
 *      Sans queue dans l'infra Floty V1, on accepte une requête HTTP
 *      synchrone potentiellement longue (loader UI + bouton désactivé).
 *
 * ADR-0013 R3 · Action légitime car coordonne plusieurs appels d'écriture
 * (chaque `GenerateInvoiceAction::execute()` est une écriture indépendante).
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
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     */
    public function execute(
        int $companyId,
        int $year,
        int $generatedByUserId,
        array $issuer,
    ): BulkInvoiceGenerationReportData {
        // Garde-fou défensif · le rendu de jusqu'à 12 PDFs peut dépasser
        // le timeout PHP par défaut (30 s). Floty V1 sans queue · on
        // accepte la requête synchrone, le bouton UI affiche un loader
        // et reste désactivé pendant l'opération.
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
                // Race condition · une autre génération concurrente a
                // posé une facture sur ce mois entre la résolution de la
                // liste et l'appel `GenerateInvoiceAction`. On le reporte
                // sans interrompre la séquence.
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
                // Tout autre échec (PDF, persistance, etc.). Le cleanup
                // PDF orphelin est déjà géré dans la transaction de
                // `GenerateInvoiceAction::execute` (catch interne avant
                // re-throw). Ici on se contente de reporter et continuer.
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
