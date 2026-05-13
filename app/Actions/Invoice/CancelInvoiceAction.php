<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Models\Invoice;
use App\Services\Invoice\InvoicePdfStorage;
use Illuminate\Support\Facades\DB;

/**
 * Annule une facture émise (Phase 14.I V1.2 · refonte historique D5.10.P).
 * Suppression physique : la row est effacée et le PDF retiré du disque.
 *
 * **Cas d'usage** : annulation explicite par l'utilisateur (facture émise
 * par erreur, doublon, ...). À distinguer du pattern régénération
 * (cf. {@see RegenerateInvoiceAction}) qui soft-delete + archive le PDF.
 *
 * **Doctrine T4 (Phase 14.P)** : la suppression du PDF est intentionnellement
 * effectuée APRÈS le commit DB pour garantir la cohérence DB ↔ filesystem.
 * Si on supprimait le PDF avant ou pendant la transaction, un échec ultérieur
 * du commit laisserait une row Invoice intacte mais un fichier disparu.
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
            // `forceDelete` (et non `delete`) : l'annulation explicite par
            // l'utilisateur efface intégralement la facture et son PDF.
            // La conservation historique est réservée au pattern
            // régénération (cf. RegenerateInvoiceAction).
            $this->writer->forceDelete($invoice);
        });

        // Commit DB OK → suppression filesystem maintenant. Idempotent :
        // un fichier déjà absent ne produit pas d'erreur.
        $this->storage->delete($pdfPath);
    }
}
