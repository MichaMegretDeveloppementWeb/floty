<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ControlDefinition;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Keeps the materialised `vehicles.controls_due_from` fresh when a GLOBAL
 * control definition changes. A definition applies to the whole fleet (anchor,
 * durations, active status, reminder window), so any change recomputes every
 * in-fleet vehicle. Rare admin action; the nightly recompute is the self-heal
 * net should any channel be missed. Wired via `#[ObservedBy]` on the model.
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
