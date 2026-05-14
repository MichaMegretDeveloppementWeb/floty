<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

/**
 * Policy contrat · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Toutes les méthodes retournent `true` en V1 · l'application n'expose
 * qu'un périmètre utilisateur global, sans scoping par société. Les
 * controllers appellent déjà `Gate::authorize(...)` pour que la logique
 * multi-tenant V2 puisse s'ajouter ici sans refactor des controllers.
 *
 * Migration V2 attendue · scoper l'accès par appartenance à la société
 * de location ou par entreprise utilisatrice destinataire selon le rôle.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D6 (F-12-001).
 */
final class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contract $contract): bool
    {
        return true;
    }

    public function delete(User $user, Contract $contract): bool
    {
        return true;
    }
}
