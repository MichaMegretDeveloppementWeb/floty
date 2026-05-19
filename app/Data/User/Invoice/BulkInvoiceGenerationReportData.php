<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rapport de fin d'exécution du `BulkGenerateInvoicesAction` (génération
 * en masse des annexes d'une (entreprise × année).
 *
 * Stratégie d'exécution best-effort · chaque mois est traité dans sa
 * propre transaction par `GenerateInvoiceAction` ; un échec sur un mois
 * n'interrompt pas la séquence. Le rapport collecte les trois issues
 * possibles ·
 *
 *   - `generated` · annexes effectivement émises (numéro figé, PDF persisté)
 *   - `failed`    · mois pour lesquels une exception domaine a été levée
 *                   (tarif manquant, race condition d'unicité, etc.)
 *   - `skipped`   · mois exclus en amont du try (déjà facturé entre temps,
 *                   pas d'activité, mois non écoulé, etc.)
 *
 * Aucune fusion n'est faite côté DTO · le contrôleur et le composant Vue
 * affichent les trois sections séparément pour rester lisibles.
 */
#[TypeScript]
final class BulkInvoiceGenerationReportData extends Data
{
    public function __construct(
        public int $companyId,
        public int $year,

        /** @var list<BulkInvoiceGenerationGeneratedItemData> */
        #[DataCollectionOf(BulkInvoiceGenerationGeneratedItemData::class)]
        public array $generated,

        /** @var list<BulkInvoiceGenerationFailedItemData> */
        #[DataCollectionOf(BulkInvoiceGenerationFailedItemData::class)]
        public array $failed,

        /** @var list<BulkInvoiceGenerationSkippedItemData> */
        #[DataCollectionOf(BulkInvoiceGenerationSkippedItemData::class)]
        public array $skipped,
    ) {}
}
