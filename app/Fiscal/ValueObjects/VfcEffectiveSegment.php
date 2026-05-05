<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;

/**
 * Segment temporel d'une VFC active dans une année fiscale donnée.
 *
 * Émis par
 * {@see App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface::findEffectiveSegmentsForYear()}.
 * Les bornes `start` / `end` sont **clippées à l'année** demandée :
 *   - `start` = max(VFC.effective_from, year-01-01)
 *   - `end`   = min(VFC.effective_to ?? year-12-31, year-12-31)
 *
 * Bornes inclusives. Un segment couvre toujours au moins 1 jour
 * (l'intersection vide n'est pas matérialisée).
 *
 * Consommé par
 * {@see App\Fiscal\Pipeline\VfcSegmentedFiscalExecutor} qui exécute
 * une sous-pipeline par segment, en posant la VFC + la
 * {@see DaysWindow} correspondante sur le contexte.
 */
final readonly class VfcEffectiveSegment
{
    public function __construct(
        public VehicleFiscalCharacteristics $vfc,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}
}
