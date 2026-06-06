<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleEvent;

/**
 * VehicleEvent policy. V1: no multi-tenant scoping yet (ships in V2, ADR-0011
 * § 7), but system-generated lifecycle events (acquisition, fleet exit) are
 * read-only · they cannot be edited or deleted manually (driven by the
 * vehicle's state).
 */
final class VehicleEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleEvent $vehicleEvent): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VehicleEvent $vehicleEvent): bool
    {
        return ! $vehicleEvent->isSystemGenerated();
    }

    public function delete(User $user, VehicleEvent $vehicleEvent): bool
    {
        return ! $vehicleEvent->isSystemGenerated();
    }
}
