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
 * Wrappé dans `DB::transaction` pour garantir cohérence DB ↔ filesystem
 * (rollback DB si la suppression filesystem échoue silencieusement —
 * en pratique `Storage::delete` est idempotent et ne lève jamais).
 */
final readonly class CancelInvoiceAction
{
    public function __construct(
        private InvoiceWriteRepositoryInterface $writer,
        private InvoicePdfStorage $storage,
    ) {}

    public function execute(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $this->storage->delete($invoice->pdf_path);
            $this->writer->delete($invoice);
        });
    }
}
