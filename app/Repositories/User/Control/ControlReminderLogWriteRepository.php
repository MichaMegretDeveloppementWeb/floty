<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlReminderLogWriteRepositoryInterface;
use App\Models\ControlReminderLog;

final class ControlReminderLogWriteRepository implements ControlReminderLogWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): ControlReminderLog
    {
        $definitionId = $attributes['control_definition_id'] ?? null;
        $overrideId = $attributes['vehicle_control_override_id'] ?? null;

        $attributes['target_key'] = ControlReminderLog::targetKey(
            $definitionId !== null ? (int) $definitionId : null,
            $overrideId !== null ? (int) $overrideId : null,
        );

        return ControlReminderLog::query()->create($attributes);
    }
}
