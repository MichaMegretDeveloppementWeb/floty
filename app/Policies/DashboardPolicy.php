<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Providers\AuthServiceProvider;

/**
 * Policy dashboard · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Pas de Model dédié · la Policy s'attache à une « intention » de
 * consultation du dashboard. Branchée via `Gate::define('view-dashboard', ...)`
 * dans {@see AuthServiceProvider::boot()}.
 *
 * Migration V2 attendue · le contenu du dashboard sera filtré par rôle
 * (gestionnaire de société vs entreprise utilisatrice).
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class DashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
