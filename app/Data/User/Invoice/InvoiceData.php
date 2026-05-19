<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\Invoice;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full invoice detail for the Show page: identity + issuance metadata +
 * vehicle lines.
 *
 * The PDF binary is not embedded; the Show page exposes a separate
 * `download` link.
 */
#[TypeScript]
final class InvoiceData extends Data
{
    /**
     * @param  list<InvoiceLineData>  $lines
     *
     * Note: `divergence` (snapshot vs current recompute) is not on this
     * DTO. The Show page receives it as a deferred root prop to avoid
     * blocking mount on a full `BillingCalculator` run.
     */
    public function __construct(
        public int $id,
        public string $invoiceNumber,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $year,
        public int $month,
        public int $totalHtCents,
        /**
         * GROSS total snapshot (= before commercial discount). Equal to
         * `totalHtCents` when no discount applied.
         */
        public int $totalGrossCents,
        /**
         * Sum of commercial discounts applied (cents). 0 when no discount.
         */
        public int $totalDiscountCents,
        public string $pdfHash,
        /** ISO 8601 (Y-m-d). */
        public string $generatedAt,
        public ?string $generatedByUserName,
        #[DataCollectionOf(InvoiceLineData::class)]
        public array $lines,
        /**
         * `true` iff this row is a soft-deleted version (superseded by a
         * regeneration). Enables the "This invoice has been replaced by
         * #XXX" banner.
         */
        public bool $isObsolete = false,
        /** Number of the invoice replacing this one. */
        public ?string $supersededByInvoiceNumber = null,
        /** ID of the replacing invoice, for navigation. */
        public ?int $supersededByInvoiceId = null,
        /** Number of the invoice this one replaces (reverse banner). */
        public ?string $supersedesInvoiceNumber = null,
        /** ID of the replaced invoice, for navigation. */
        public ?int $supersedesInvoiceId = null,
        /**
         * Full version chain of the (company × year × month) tuple,
         * unordered (front-end sorts by `id DESC`). Includes the current
         * version itself. Empty or singleton when never regenerated.
         *
         * @var list<InvoiceHistoryEntryData>
         */
        public array $historyChain = [],
    ) {}

    /**
     * @param  list<Invoice>  $historyChain  All versions of the tuple, any order (sorted on the client).
     */
    public static function fromModel(
        Invoice $invoice,
        ?Invoice $predecessor = null,
        array $historyChain = [],
    ): self {
        $lines = $invoice->lines
            ->map(static fn ($l): InvoiceLineData => InvoiceLineData::fromModel($l))
            ->values()
            ->all();

        $supersededBy = null;
        $supersededById = null;
        if ($invoice->superseded_by_id !== null && $invoice->relationLoaded('supersededBy')) {
            $supersededBy = $invoice->supersededBy?->invoice_number;
            $supersededById = $invoice->supersededBy?->id;
        }

        $historyEntries = array_map(
            static fn (Invoice $i): InvoiceHistoryEntryData => InvoiceHistoryEntryData::fromModel($i),
            $historyChain,
        );

        return new self(
            id: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            companyId: $invoice->company_id,
            companyShortCode: $invoice->company->short_code,
            companyLegalName: $invoice->company->legal_name,
            year: $invoice->year,
            month: $invoice->month,
            totalHtCents: $invoice->total_ht_cents,
            totalGrossCents: $invoice->total_gross_cents,
            totalDiscountCents: $invoice->total_discount_cents,
            pdfHash: $invoice->pdf_hash,
            generatedAt: $invoice->generated_at->toDateString(),
            generatedByUserName: trim($invoice->generatedBy->first_name.' '.$invoice->generatedBy->last_name),
            lines: $lines,
            isObsolete: $invoice->deleted_at !== null,
            supersededByInvoiceNumber: $supersededBy,
            supersededByInvoiceId: $supersededById,
            supersedesInvoiceNumber: $predecessor?->invoice_number,
            supersedesInvoiceId: $predecessor?->id,
            historyChain: $historyEntries,
        );
    }
}
