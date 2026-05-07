<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de l'Index Invoices (Phase 14.F V1.2). Identité minimale +
 * total + métadonnées d'émission, sans les lignes détaillées.
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
         * `true` si le périmètre contractuel a changé depuis l'émission.
         * Calculé par `InvoiceQueryService::listPaginated` via
         * `InvoiceDivergenceChecker`. La liste se contente d'un signal
         * binaire — les valeurs détaillées sont sur la fiche Show.
         */
        public bool $hasDivergence = false,
    ) {}

    public static function fromModel(Invoice $invoice, bool $hasDivergence = false): self
    {
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
            hasDivergence: $hasDivergence,
        );
    }
}
