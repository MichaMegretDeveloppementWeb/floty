<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unavailability;
use App\Models\User;

/**
 * Policy indisponibilité · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Toutes les méthodes retournent `true` en V1. Les controllers appellent
 * `Gate::authorize(...)` pour préparer V2 multi-tenant sans refactor.
 *
 * Migration V2 attendue · scoper l'accès par société propriétaire du
 * véhicule porteur de l'indisponibilité.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
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
