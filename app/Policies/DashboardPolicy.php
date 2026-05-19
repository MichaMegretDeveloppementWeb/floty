<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Providers\AuthServiceProvider;

/**
 * Dashboard policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 *
 * Bound to the `view-dashboard` ability in {@see AuthServiceProvider::boot()}.
 */
final class DashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
