<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Entrée minimale de la timeline historique d'une facture (Phase D5.10.P
 * option C). Une entrée par version connue du couple (entreprise × année
 * × mois) · ordre par `id DESC` côté composant front (plus récent en haut).
 *
 * Champs identitaires uniquement (pas de divergence, pas de lignes) ·
 * la timeline est purement présentationnelle, l'utilisateur clique sur
 * une entrée pour ouvrir la fiche Show de la version souhaitée.
 */
#[TypeScript]
final class InvoiceHistoryEntryData extends Data
{
    public function __construct(
        public int $id,
        public string $invoiceNumber,
        /** ISO 8601 (Y-m-d). */
        public string $generatedAt,
        /** `true` ssi soft-deletée (remplacée par une régénération). */
        public bool $isObsolete,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            generatedAt: $invoice->generated_at->toDateString(),
            isObsolete: $invoice->deleted_at !== null,
        );
    }
}
