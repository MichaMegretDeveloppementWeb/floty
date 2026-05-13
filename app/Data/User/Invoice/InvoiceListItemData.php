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
         * `true` si le périmètre contractuel a potentiellement changé
         * depuis l'émission. Lu directement sur la colonne matérialisée
         * `invoices.is_divergent` (T6 / Phase 14.R) · flag posé par les
         * observers (Contract / VehicleYearlyPricing / Vehicle.exit_date).
         * La liste se contente d'un signal binaire ; les valeurs détaillées
         * sont sur la fiche Show via `InvoiceDivergenceChecker`.
         */
        public bool $hasDivergence = false,
        /**
         * `true` ssi cette ligne est une version soft-deletée (régénération
         * antérieure). Visible uniquement quand le filtre Index
         * `includeObsolete = true`. Permet de griser la ligne et d'afficher
         * la mention « Remplacée par #YYYY-MM-NNNN ».
         */
        public bool $isObsolete = false,
        /**
         * Numéro de la facture qui remplace celle-ci · `null` pour les
         * factures actives et pour les obsolètes orphelines (rare).
         */
        public ?string $supersededByInvoiceNumber = null,
        /** ID de la facture qui remplace · pour générer le lien navigation. */
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
