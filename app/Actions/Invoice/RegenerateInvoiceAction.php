<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Models\Invoice;
use App\Services\Invoice\InvoicePdfStorage;
use Illuminate\Support\Facades\DB;

/**
 * Régénère une facture émise (Phase 14.I+ V1.2). Combine en une seule
 * transaction DB l'annulation de la facture existante (suppression de la
 * row + ses lignes en cascade) puis la génération d'une nouvelle facture
 * pour le même couple (entreprise × année × mois) avec les données
 * contractuelles actuelles.
 *
 * **Cas d'usage** : un contrat est ajouté/modifié/supprimé sur un mois
 * déjà facturé. L'UI signale la divergence à l'utilisateur, qui clique
 * « Régénérer » et obtient une nouvelle facture cohérente avec la
 * réalité actuelle. La doctrine immuabilité (ADR-0008) est respectée :
 * la régénération est une suppression-puis-création explicite, pas une
 * mutation in-place.
 *
 * **Doctrine T4 (Phase 14.P) · cohérence DB ↔ filesystem** : la
 * suppression de l'**ancien PDF** sur disque est différée APRÈS le commit
 * DB de la nouvelle facture. Si `Generate` throw (ex. `MissingPricingException`),
 * la transaction rollback restaure intégralement l'ancienne facture en DB
 * **et** l'ancien PDF n'a jamais été touché : état cohérent garanti.
 *
 * **Composition** : délègue à {@see CancelInvoiceAction::executeKeepingPdf}
 * (suppression DB sans toucher au PDF) puis {@see GenerateInvoiceAction::execute}
 * dans la transaction commune ; supprime l'ancien PDF hors transaction.
 */
final readonly class RegenerateInvoiceAction
{
    public function __construct(
        private CancelInvoiceAction $cancel,
        private GenerateInvoiceAction $generate,
        private InvoicePdfStorage $pdfStorage,
    ) {}

    /**
     * @param  array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}  $issuer
     */
    public function execute(Invoice $invoice, int $generatedByUserId, array $issuer): Invoice
    {
        $companyId = (int) $invoice->company_id;
        $year = (int) $invoice->year;
        $month = (int) $invoice->month;
        $oldPdfPath = $invoice->pdf_path;

        // Sauvegarde l'ancien PDF en mémoire avant de le retirer du disque.
        // Justification : le `Generate` qui suit calcule un `pdf_path` à
        // partir de `(year, companyId, invoice_number)` ; après suppression
        // de la row, le séquenceur peut réattribuer le même numéro et donc
        // le même path. Pour libérer le path, on doit supprimer l'ancien
        // PDF ; pour garantir la cohérence en cas d'échec, on garde sa
        // copie en mémoire et on la restaure lors d'un rollback.
        $oldPdfBinary = $this->pdfStorage->read($oldPdfPath);

        // Libère le path pour permettre au `Generate` de réutiliser le même.
        $this->pdfStorage->delete($oldPdfPath);

        try {
            return DB::transaction(function () use ($invoice, $generatedByUserId, $issuer, $companyId, $year, $month): Invoice {
                $this->cancel->executeKeepingPdf($invoice);

                return $this->generate->execute(
                    companyId: $companyId,
                    year: $year,
                    month: $month,
                    generatedByUserId: $generatedByUserId,
                    issuer: $issuer,
                );
            });
        } catch (\Throwable $e) {
            // Generate a échoué → rollback DB restaure la row Invoice qui
            // pointe vers `$oldPdfPath`. On restaure le binaire pour ne
            // pas laisser la facture pointer vers un fichier disparu.
            if ($oldPdfBinary !== null) {
                $this->pdfStorage->restoreFromBackup($oldPdfPath, $oldPdfBinary);
            }

            throw $e;
        }
    }
}
