<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContractDocument;
use App\Models\User;

/**
 * Policy document contrat · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Toutes les méthodes retournent `true` en V1. Préparation V2 multi-tenant ·
 * scoper par société propriétaire du contrat parent.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D6 (F-12-001).
 */
final class ContractDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContractDocument $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, ContractDocument $document): bool
    {
        return true;
    }
}
