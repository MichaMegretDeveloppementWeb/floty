<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\Invoice;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail complet d'une facture pour la page Show (Phase 14.F V1.2) :
 * identité + métadonnées émission + lignes véhicule.
 *
 * Le PDF n'est pas embarqué dans le DTO ; la page Show propose un
 * lien `download` séparé qui sert le binaire.
 */
#[TypeScript]
final class InvoiceData extends Data
{
    /**
     * @param  list<InvoiceLineData>  $lines
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
        public string $pdfHash,
        /** ISO 8601 (Y-m-d). */
        public string $generatedAt,
        public ?string $generatedByUserName,
        #[DataCollectionOf(InvoiceLineData::class)]
        public array $lines,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $lines = $invoice->lines
            ->map(static fn ($l): InvoiceLineData => InvoiceLineData::fromModel($l))
            ->values()
            ->all();

        return new self(
            id: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            companyId: $invoice->company_id,
            companyShortCode: $invoice->company->short_code,
            companyLegalName: $invoice->company->legal_name,
            year: $invoice->year,
            month: $invoice->month,
            totalHtCents: $invoice->total_ht_cents,
            pdfHash: $invoice->pdf_hash,
            generatedAt: $invoice->generated_at->toDateString(),
            generatedByUserName: trim($invoice->generatedBy->first_name.' '.$invoice->generatedBy->last_name),
            lines: $lines,
        );
    }
}
