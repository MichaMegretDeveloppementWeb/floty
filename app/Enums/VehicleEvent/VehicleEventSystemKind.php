<?php

declare(strict_types=1);

namespace App\Enums\VehicleEvent;

/**
 * Marks a vehicle event as system-generated (a lifecycle marker), as opposed
 * to a user-created event. System events are read-only (no manual edit/delete)
 * and driven by the vehicle's state:
 *   - Acquisition: created with the vehicle, on its acquisition date ;
 *   - FleetExit: created on fleet exit, removed when the exit is cancelled.
 */
enum VehicleEventSystemKind: string
{
    case Acquisition = 'acquisition';
    case FleetExit = 'fleet_exit';

    /** Fixed title shown for this lifecycle event. */
    public function title(): string
    {
        return match ($this) {
            self::Acquisition => 'Entrée en flotte',
            self::FleetExit => 'Sortie de flotte',
        };
    }
}
