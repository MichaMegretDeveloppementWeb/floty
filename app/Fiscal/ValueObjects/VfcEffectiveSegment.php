<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;

/**
 * Temporal segment over which a VFC is active inside a fiscal year.
 *
 * Emitted by
 * {@see VehicleFiscalCharacteristicsReadRepositoryInterface::findEffectiveSegmentsForYear()}.
 * The `start` / `end` bounds are **clipped to the year** requested:
 *   - `start` = max(VFC.effective_from, year-01-01)
 *   - `end`   = min(VFC.effective_to ?? year-12-31, year-12-31)
 *
 * Bounds inclusive. A segment always spans at least one day (empty
 * intersections are not materialised).
 *
 * Consumed by {@see FiscalSegmentedExecutor},
 * which runs a sub-pipeline per segment with the matching VFC and
 * {@see DaysWindow} on the context.
 */
final readonly class VfcEffectiveSegment
{
    public function __construct(
        public VehicleFiscalCharacteristics $vfc,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}
}
