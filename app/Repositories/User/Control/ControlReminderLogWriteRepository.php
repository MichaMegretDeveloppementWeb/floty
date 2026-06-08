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

        // A global control customised by a vehicle override carries BOTH a
        // definition id and an override id, but `chk_crl_target` allows exactly
        // one non-null FK. The dedup identity (target_key) is the definition
        // when present, so journal against it and drop the override id: writing
        // both would violate the CHECK, abort the insert, and make the reminder
        // re-send on every run (the occurrence would never be journaled).
        if ($definitionId !== null) {
            $attributes['vehicle_control_override_id'] = null;
        }

        return ControlReminderLog::query()->create($attributes);
    }
}
