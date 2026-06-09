<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VehicleControlOverride;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Recomputes the `controls_due_from` cache for the override's vehicle.
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
