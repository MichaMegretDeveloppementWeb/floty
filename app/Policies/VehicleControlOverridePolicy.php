<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleControlOverride;

/**
 * Per-vehicle control overrides policy. V1 stub returning `true`; multi-tenant
 * scoping ships in V2 (ADR-0011 § 7), mirroring the other V1 policies.
 */
final class VehicleControlOverridePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VehicleControlOverride $override): bool
    {
        return true;
    }

    public function delete(User $user, VehicleControlOverride $override): bool
    {
        return true;
    }
}
