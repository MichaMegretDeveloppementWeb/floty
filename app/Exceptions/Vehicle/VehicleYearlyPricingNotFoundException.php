<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * Aucun tarif trouvé pour le couple (vehicle_id, year) demandé.
 *
 * Levée par {@see App\Actions\Vehicle\DeleteVehicleYearlyPricingAction}
 * quand l'utilisateur tente de supprimer un tarif inexistant. Le
 * `BillingCalculator` utilisera sa propre `MissingPricingException`
 * (chantier 14.C) pour le cas « calcul facture sans tarif » qui demande
 * un message UX plus contextualisé.
 */
final class VehicleYearlyPricingNotFoundException extends BaseAppException
{
    public static function forVehicleAndYear(int $vehicleId, int $year): self
    {
        return new self(
            technicalMessage: "No yearly pricing found for vehicle #{$vehicleId} and year {$year}.",
            userMessage: "Aucun tarif n'est enregistré pour l'année {$year} sur ce véhicule.",
        );
    }
}
