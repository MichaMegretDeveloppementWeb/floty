<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\VehicleEvent\VehicleEventData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * "Current state" of the vehicle as of today, for the card at the top of the
 * overview tab: the event(s) spanning today (ongoing) and the rental(s)
 * active today (with their drivers). Both are empty for a vehicle out of the
 * fleet (the overview header already surfaces the exit) and when nothing is
 * in progress today (the vehicle is simply available).
 *
 * `events` reuses the existing {@see VehicleEventData} (already mapped for the
 * base payload), filtered to those overlapping today, so no extra query is
 * paid for them; only the active-rental lookup adds one bounded query.
 */
#[TypeScript]
final class CurrentVehicleStatusData extends Data
{
    /**
     * @param  list<VehicleEventData>  $events  Events overlapping today (start <= today <= end, or open-ended).
     * @param  list<CurrentRentalData>  $rentals  Contracts active today (0..1 in practice).
     */
    public function __construct(
        #[DataCollectionOf(VehicleEventData::class)]
        public array $events,
        #[DataCollectionOf(CurrentRentalData::class)]
        public array $rentals,
    ) {}
}
