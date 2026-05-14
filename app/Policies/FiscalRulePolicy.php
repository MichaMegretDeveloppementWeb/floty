<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Providers\AuthServiceProvider;

/**
 * Policy règles fiscales · stub V1 mono-tenant (ADR-0011 § 7).
 *
 * Pas de Model dédié · la Policy s'attache à une « intention » de
 * consultation du catalogue des règles. Branchée via
 * `Gate::define('view-fiscal-rules', ...)` dans
 * {@see AuthServiceProvider::boot()}.
 *
 * Migration V2 attendue · les règles fiscales restent publiques (lecture
 * universelle pour tout utilisateur authentifié), donc cette Policy a
 * peu de chances de devenir plus stricte. Conservée pour cohérence du
 * pattern et pour pouvoir scoper par année si jamais on segmente
 * l'accès aux règles historiques.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class FiscalRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
