<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ControlReminderSettings;
use App\Models\User;

/**
 * Global reminder settings policy (singleton). V1 stub returning `true`; multi-tenant
 * scoping ships in V2 (ADR-0011 § 7), mirroring {@see FiscalRiskSettingsPolicy}.
 *
 * No `viewAny` / `delete` ability: the reminder settings are an application singleton.
 */
final class ControlReminderSettingsPolicy
{
    public function view(User $user, ControlReminderSettings $settings): bool
    {
        return true;
    }

    public function update(User $user, ControlReminderSettings $settings): bool
    {
        return true;
    }
}
