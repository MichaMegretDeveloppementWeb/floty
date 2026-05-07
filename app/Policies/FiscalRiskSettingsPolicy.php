<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FiscalRiskSettings;
use App\Models\User;

/**
 * Policy paramètres détection de risque fiscal (singleton seuils).
 * Stub V1 mono-tenant (Phase 11 D1). Cf. {@see BillingSettingsPolicy}
 * pour la doctrine globale.
 *
 * Pas d'ability `viewAny` ni `delete` : c'est un singleton applicatif
 * (toujours présent, jamais supprimable). Seul `view` (page Settings)
 * et `update` ont du sens.
 */
final class FiscalRiskSettingsPolicy
{
    public function view(User $user, FiscalRiskSettings $settings): bool
    {
        return true;
    }

    public function update(User $user, FiscalRiskSettings $settings): bool
    {
        return true;
    }
}
