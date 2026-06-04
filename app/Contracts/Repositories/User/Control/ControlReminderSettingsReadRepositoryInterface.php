<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Data\User\Control\ControlRecipientData;
use App\Models\ControlReminderSettings;

/**
 * Reads of the global reminder configuration (singleton + level-0 default
 * recipients), Chantier B / B1.
 */
interface ControlReminderSettingsReadRepositoryInterface
{
    /**
     * Returns the unique settings row (auto-created when the table is empty).
     */
    public function get(): ControlReminderSettings;

    /**
     * Returns the level-0 default recipients (settings-level include deltas),
     * ordered for display.
     *
     * @return array<int, ControlRecipientData>
     */
    public function defaultRecipients(): array;
}
