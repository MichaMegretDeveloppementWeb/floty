<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ControlReminderSettings;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Keeps the materialised `vehicles.controls_due_from` fresh when the GLOBAL
 * default reminder window changes: `days_before` is baked into `dueFrom`
 * (= next_due - window) for every control that inherits the default, so the
 * whole fleet is recomputed. The other settings fields (repeat, remind-on-due)
 * do not affect the due-from date. Wired via `#[ObservedBy]` on the model.
 */
final readonly class ControlReminderSettingsObserver
{
    public function __construct(
        private ControlDueDateRecomputeService $recompute,
    ) {}

    public function updated(ControlReminderSettings $settings): void
    {
        if (! $settings->wasChanged('days_before')) {
            return;
        }

        $this->recompute->forFleet();
    }
}
