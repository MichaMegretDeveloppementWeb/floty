<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal entry in the version history timeline of an invoice
 * (company × year × month). Ordered `id DESC` by the front-end (newest
 * on top).
 *
 * Identity fields only (no divergence, no lines): the timeline is purely
 * presentational and clicking a row opens the Show page of that version.
 */
#[TypeScript]
final class InvoiceHistoryEntryData extends Data
{
    public function __construct(
        public int $id,
        public string $invoiceNumber,
        /** ISO 8601 (Y-m-d). */
        public string $generatedAt,
        /** `true` iff soft-deleted (superseded by a regeneration). */
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
