<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Data\User\Control\ControlReminderSettingsData;
use App\Models\ControlReminderSettings;

/**
 * Writes of the global reminder configuration (singleton + level-0 default
 * recipients), Chantier B / B1.
 */
interface ControlReminderSettingsWriteRepositoryInterface
{
    /**
     * Updates the singleton row and re-syncs the level-0 default recipients
     * (`settings` include deltas) from the DTO, atomically.
     */
    public function update(ControlReminderSettingsData $data): ControlReminderSettings;
}
