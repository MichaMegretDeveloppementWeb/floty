<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Enums\Invoice\BulkInvoiceGenerationSkipReason;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Mois exclus de la génération avant tout appel à `GenerateInvoiceAction`.
 * Cas attendus · facture déjà émise (race entre clic et bulk), aucun jour
 * utilisé, mois non encore écoulé, tarif annuel manquant.
 */
#[TypeScript]
final class BulkInvoiceGenerationSkippedItemData extends Data
{
    public function __construct(
        public int $month,
        public BulkInvoiceGenerationSkipReason $reason,
    ) {}
}
