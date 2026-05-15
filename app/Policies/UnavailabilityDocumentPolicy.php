<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnavailabilityDocument;
use App\Models\User;

/**
 * Policy document indisponibilité · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Toutes les méthodes retournent `true` en V1. Préparation V2 multi-tenant ·
 * scoper par société propriétaire du véhicule via l'indispo parent · les
 * controllers ne changeront pas.
 *
 * Aligné sur {@see ContractDocumentPolicy}.
 */
final class UnavailabilityDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UnavailabilityDocument $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, UnavailabilityDocument $document): bool
    {
        return true;
    }
}
