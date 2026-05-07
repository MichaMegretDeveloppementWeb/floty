<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Models\Invoice;
use App\Services\Invoice\InvoicePdfStorage;
use Illuminate\Support\Facades\DB;

/**
 * Annule une facture émise (Phase 14.I V1.2). Seule mutation autorisée
 * par la doctrine immuabilité (ADR-0008) : supprime le PDF du disque
 * + l'enregistrement de la facture (cascade des `invoice_lines` via
 * la contrainte `ON DELETE CASCADE` de la migration).
 *
 * **Cas d'usage** : un contrat est ajouté/modifié/supprimé a posteriori
 * sur un mois déjà facturé → divergence détectée par l'UI → utilisateur
 * annule pour pouvoir régénérer la facture avec les données actuelles.
 *
 * **Doctrine T4 (Phase 14.P)** : la suppression du PDF est intentionnellement
 * effectuée APRÈS le commit DB pour garantir la cohérence DB ↔ filesystem.
 * Si on supprimait le PDF avant ou pendant la transaction, un échec ultérieur
 * du commit laisserait une row Invoice intacte mais un fichier disparu.
 *
 * Pour la composition par {@see RegenerateInvoiceAction}, utiliser plutôt
 * {@see executeKeepingPdf()} qui retourne le `pdf_path` à supprimer plus tard
 * (après commit du `Generate` qui suit).
 */
final readonly class CancelInvoiceAction
{
    public function __construct(
        private InvoiceWriteRepositoryInterface $writer,
        private InvoicePdfStorage $storage,
    ) {}

    public function execute(Invoice $invoice): void
    {
        $pdfPath = $invoice->pdf_path;

        DB::transaction(function () use ($invoice): void {
            $this->writer->delete($invoice);
        });

        // Commit DB OK → suppression filesystem maintenant. Idempotent :
        // un fichier déjà absent ne produit pas d'erreur.
        $this->storage->delete($pdfPath);
    }

    /**
     * Variante orchestrée pour {@see RegenerateInvoiceAction} : supprime
     * uniquement la row DB (en transaction) sans toucher au PDF, et
     * retourne le `pdf_path` que le caller orchestrera après son propre
     * commit. Garantit que si la suite (Generate) échoue, la transaction
     * rollback restaure la facture **et** l'ancien PDF n'a jamais été touché.
     */
    public function executeKeepingPdf(Invoice $invoice): string
    {
        $pdfPath = $invoice->pdf_path;

        DB::transaction(function () use ($invoice): void {
            $this->writer->delete($invoice);
        });

        return $pdfPath;
    }
}
