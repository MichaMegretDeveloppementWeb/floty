<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Providers\AuthServiceProvider;

/**
 * Policy planning · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Pas de Model dédié · la Policy s'attache à une « intention » de
 * consultation du planning. Branchée via `Gate::define('view-planning', ...)`
 * dans {@see AuthServiceProvider::boot()}.
 *
 * Migration V2 attendue · scoper l'accès par société dont l'utilisateur
 * est gestionnaire ou par entreprise utilisatrice destinataire selon le rôle.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class PlanningPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
