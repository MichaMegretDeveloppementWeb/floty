<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Models\ControlReminderLog;

/**
 * Writes of the reminder idempotence journal (Chantier B / B3).
 */
interface ControlReminderLogWriteRepositoryInterface
{
    /**
     * Append a journal row for a sent occurrence. The `target_key` is derived
     * from the def/override ids in `$attributes`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): ControlReminderLog;
}
