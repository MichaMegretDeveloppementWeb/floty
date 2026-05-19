<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

/**
 * Driver policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 */
final class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Driver $driver): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Driver $driver): bool
    {
        return true;
    }

    public function delete(User $user, Driver $driver): bool
    {
        return true;
    }
}
