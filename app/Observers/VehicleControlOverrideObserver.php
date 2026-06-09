<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VehicleControlOverride;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Keeps the materialised `vehicles.controls_due_from` fresh when a per-vehicle
 * control override changes (anchor, durations, status, reminder window). Scoped
 * to the single affected vehicle. Wired via `#[ObservedBy]` on the model.
 */
final readonly class VehicleControlOverrideObserver
{
    public function __construct(
        private ControlDueDateRecomputeService $recompute,
    ) {}

    public function saved(VehicleControlOverride $override): void
    {
        $this->recompute->forVehicleId($override->vehicle_id);
    }

    public function deleted(VehicleControlOverride $override): void
    {
        $this->recompute->forVehicleId($override->vehicle_id);
    }

    public function restored(VehicleControlOverride $override): void
    {
        $this->recompute->forVehicleId($override->vehicle_id);
    }
}
