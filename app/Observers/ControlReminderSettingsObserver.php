<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ControlReminderSettings;
use App\Services\Control\ControlDueDateRecomputeService;

/**
 * Recomputes the `controls_due_from` cache fleet-wide when the default reminder
 * window (`days_before`) changes; the other settings fields do not affect it.
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
