<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unavailability;
use App\Models\User;

/**
 * Unavailability policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 */
final class UnavailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Unavailability $unavailability): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Unavailability $unavailability): bool
    {
        return true;
    }

    public function delete(User $user, Unavailability $unavailability): bool
    {
        return true;
    }
}
