<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FiscalRiskSettings;
use App\Models\User;

/**
 * Fiscal risk settings policy (singleton). V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 *
 * No `viewAny` / `delete` ability: risk thresholds are an application singleton.
 */
final class FiscalRiskSettingsPolicy
{
    public function view(User $user, FiscalRiskSettings $settings): bool
    {
        return true;
    }

    public function update(User $user, FiscalRiskSettings $settings): bool
    {
        return true;
    }
}
