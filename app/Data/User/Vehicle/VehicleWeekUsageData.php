<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One ISO week cell in the vehicle usage timeline, listing per-company segments
 * and unavailability overlays.
 */
#[TypeScript]
final class VehicleWeekUsageData extends Data
{
    /**
     * @param  list<VehicleWeekSegmentData>  $segments
     * @param  int  $reductiveUnavailabilityDays  Unavailability days reducing the
     *                                            fiscal prorata numerator (R-2024-008).
     * @param  int  $nonReductiveUnavailabilityDays  Operational unavailability days
     *                                               without fiscal impact.
     */
    public function __construct(
        public int $weekNumber,
        #[DataCollectionOf(VehicleWeekSegmentData::class)]
        public array $segments,
        public int $totalDays,
        public int $reductiveUnavailabilityDays,
        public int $nonReductiveUnavailabilityDays,
    ) {}
}
