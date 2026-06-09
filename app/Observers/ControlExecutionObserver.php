<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ControlExecution;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Keeps the materialised `vehicles.controls_due_from` fresh when an execution is
 * recorded or removed: the latest execution date shifts the next due date
 * forward (or back, on deletion). Scoped to the affected vehicle. Wired via
 * `#[ObservedBy]` on the model.
 */
final readonly class ControlExecutionObserver
{
    public function __construct(
        private ControlDueDateRecomputeService $recompute,
    ) {}

    public function created(ControlExecution $execution): void
    {
        $this->recompute->forVehicleId($execution->vehicle_id);
    }

    public function deleted(ControlExecution $execution): void
    {
        $this->recompute->forVehicleId($execution->vehicle_id);
    }

    public function restored(ControlExecution $execution): void
    {
        $this->recompute->forVehicleId($execution->vehicle_id);
    }
}
