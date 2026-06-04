<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Day slot in the planning drawer's week grid.
 *
 *   - `contract` is `null` when the day is free OR the day belongs to
 *     another company in company-locked mode (in which case
 *     `isOccupiedByOther` is `true`).
 *   - `hasVehicleEvent` is `true` when at least one unavailability
 *     covers this day; feeds the red border of the slot.
 *   - `isOccupiedByOther` is `true` when the drawer is open on a
 *     company view and the covering contract belongs to another company;
 *     identity and colour of the other company are not leaked.
 */
#[TypeScript]
final class WeekDaySlotData extends Data
{
    public function __construct(
        public string $date,
        public string $dayLabel,
        public ?WeekDayContractData $contract,
        public bool $hasVehicleEvent,
        public bool $isOccupiedByOther = false,
    ) {}
}
