<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleYearlyPricing;

/**
 * Policy tarifs annuels véhicule : stub V1 mono-tenant (Phase 14.O).
 * Cf. {@see InvoicePolicy} pour la doctrine globale.
 *
 * Les tarifs sont rattachés à un véhicule de la société de location ; en
 * V2, l'autorisation pourrait dépendre de l'appartenance de l'utilisateur
 * à la société propriétaire de la flotte.
 */
final class VehicleYearlyPricingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleYearlyPricing $pricing): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VehicleYearlyPricing $pricing): bool
    {
        return true;
    }

    public function delete(User $user, VehicleYearlyPricing $pricing): bool
    {
        return true;
    }
}
