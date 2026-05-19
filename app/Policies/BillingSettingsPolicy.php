<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BillingSettings;
use App\Models\User;

/**
 * Billing settings policy (singleton). V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 *
 * No `viewAny` / `delete` ability: issuer settings are an application singleton.
 */
final class BillingSettingsPolicy
{
    public function view(User $user, BillingSettings $settings): bool
    {
        return true;
    }

    public function update(User $user, BillingSettings $settings): bool
    {
        return true;
    }
}
