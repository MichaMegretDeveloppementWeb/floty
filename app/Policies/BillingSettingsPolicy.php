<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BillingSettings;
use App\Models\User;

/**
 * Policy paramètres facturation (singleton émetteur) : stub V1 mono-tenant
 * (Phase 14.O). Cf. {@see InvoicePolicy} pour la doctrine globale.
 *
 * Pas d'ability `viewAny` ni `delete` : les paramètres émetteur sont un
 * singleton applicatif (toujours présent, jamais supprimable). Seul
 * `view` (page Settings) et `update` ont du sens.
 */
final class BillingSettingsPolicy
{
    public function view(User $user, BillingSettings $settings): bool
    {
        return true;
    }

    public function update(User $user, BillingSettings $settings): bool
    {
        return true;
    }
}
