<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ControlExecution;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Recomputes the `controls_due_from` cache for the execution's vehicle (a
 * recorded or removed execution shifts the next due date).
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
