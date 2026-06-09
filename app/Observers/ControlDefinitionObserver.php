<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ControlDefinition;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Recomputes the `controls_due_from` cache fleet-wide when a global control
 * definition changes (it applies to every vehicle).
 */
final readonly class ControlDefinitionObserver
{
    public function __construct(
        private ControlDueDateRecomputeService $recompute,
    ) {}

    public function saved(ControlDefinition $definition): void
    {
        $this->recompute->forFleet();
    }

    public function deleted(ControlDefinition $definition): void
    {
        $this->recompute->forFleet();
    }

    public function restored(ControlDefinition $definition): void
    {
        $this->recompute->forFleet();
    }
}
