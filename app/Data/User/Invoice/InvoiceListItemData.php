<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Invoice row for the Invoices index. Minimal identity + total + issuance
 * metadata, without detailed lines.
 */
#[TypeScript]
final class InvoiceListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $invoiceNumber,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $year,
        public int $month,
        public int $totalHtCents,
        /** ISO 8601 (Y-m-d). */
        public string $generatedAt,
        /**
         * `true` if the contractual scope may have changed since issuance.
         * Read directly from the materialised `invoices.is_divergent`
         * column (flag set by observers on Contract / VehicleYearlyPricing
         * / Vehicle.exit_date). The list only carries a binary signal;
         * detailed values are on the Show page via
         * `InvoiceDivergenceChecker`.
         */
        public bool $hasDivergence = false,
        /**
         * `true` iff this row is a soft-deleted older version (previously
         * regenerated). Visible only when the index filter `includeObsolete
         * = true`. Lets the UI grey the row and surface a "Replaced by
         * #YYYY-MM-NNNN" mention.
         */
        public bool $isObsolete = false,
        /**
         * Number of the invoice replacing this one. `null` for active rows
         * and for orphan obsolete rows (rare).
         */
        public ?string $supersededByInvoiceNumber = null,
        /** ID of the replacing invoice, for navigation links. */
        public ?int $supersededByInvoiceId = null,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $supersededBy = null;
        $supersededById = null;
        if ($invoice->superseded_by_id !== null && $invoice->relationLoaded('supersededBy')) {
            $supersededBy = $invoice->supersededBy?->invoice_number;
            $supersededById = $invoice->supersededBy?->id;
        }

        return new self(
            id: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            companyId: $invoice->company_id,
            companyShortCode: $invoice->company->short_code,
            companyLegalName: $invoice->company->legal_name,
            year: $invoice->year,
            month: $invoice->month,
            totalHtCents: $invoice->total_ht_cents,
            generatedAt: $invoice->generated_at->toDateString(),
            hasDivergence: $invoice->is_divergent,
            isObsolete: $invoice->deleted_at !== null,
            supersededByInvoiceNumber: $supersededBy,
            supersededByInvoiceId: $supersededById,
        );
    }
}
