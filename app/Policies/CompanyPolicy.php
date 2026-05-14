<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Policy entreprise · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Toutes les méthodes retournent `true` en V1. Les controllers appellent
 * `Gate::authorize(...)` pour préparer V2 multi-tenant sans refactor.
 *
 * Migration V2 attendue · scoper l'accès par appartenance à la société
 * de location ou par entreprise utilisatrice destinataire selon le rôle.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return true;
    }

    public function delete(User $user, Company $company): bool
    {
        return true;
    }
}
