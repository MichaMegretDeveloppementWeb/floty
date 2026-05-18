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
     *
     * Note · la `divergence` (comparaison snapshot vs reel actuel)
     * n'est PAS dans ce DTO. Elle est servie cote Show via
     * `Inertia::defer` en prop racine `divergence` (audit perf
     * 2026-05-16 / 06-invoices.md P1 #1) pour ne pas bloquer le mount
     * sur un BillingCalculator complet.
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
         * Total BRUT (= avant réduction commerciale) snapshot.
         * Égal à `totalHtCents` quand aucune réduction appliquée.
         * Lot 3 réductions commerciales.
         */
        public int $totalGrossCents,
        /**
         * Total des réductions commerciales appliquées (cents).
         * 0 si aucune réduction. Lot 3 réductions commerciales.
         */
        public int $totalDiscountCents,
        public string $pdfHash,
        /** ISO 8601 (Y-m-d). */
        public string $generatedAt,
        public ?string $generatedByUserName,
        #[DataCollectionOf(InvoiceLineData::class)]
        public array $lines,
        /**
         * `true` ssi la facture est une version obsolète (soft-deletée
         * par une régénération). Permet l'affichage bandeau « Cette
         * facture a été remplacée par #XXX ».
         */
        public bool $isObsolete = false,
        /** Numéro de la facture qui remplace celle-ci. */
        public ?string $supersededByInvoiceNumber = null,
        /** ID de la facture qui remplace · pour la navigation. */
        public ?int $supersededByInvoiceId = null,
        /** Numéro de la facture remplacée par celle-ci · bandeau inverse. */
        public ?string $supersedesInvoiceNumber = null,
        /** ID de la facture remplacée · pour la navigation. */
        public ?int $supersedesInvoiceId = null,
        /**
         * Chaîne historique complète des versions du couple (entreprise ×
         * année × mois) · liste non triée (le front trie par `id DESC`
         * = plus récent en haut). Inclut la version courante elle-même.
         * Vide ou singleton si jamais régénérée.
         *
         * @var list<InvoiceHistoryEntryData>
         */
        public array $historyChain = [],
    ) {}

    /**
     * @param  list<Invoice>  $historyChain  les versions du couple, dans un ordre quelconque (trié front)
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
