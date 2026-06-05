<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlReminderLogReadRepositoryInterface;
use App\Models\ControlReminderLog;

final class ControlReminderLogReadRepository implements ControlReminderLogReadRepositoryInterface
{
    public function existsForOccurrence(
        int $vehicleId,
        ?int $definitionId,
        ?int $overrideId,
        string $dueOn,
        string $reminderOn,
    ): bool {
        return ControlReminderLog::query()
            ->where('vehicle_id', $vehicleId)
            ->where('target_key', ControlReminderLog::targetKey($definitionId, $overrideId))
            ->where('due_on', $dueOn)
            ->where('reminder_on', $reminderOn)
            ->exists();
    }
}
