<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * No yearly pricing found for the given (vehicle_id, year) pair.
 * The billing pipeline uses {@see App\Exceptions\Billing\MissingPricingException} instead for invoice-time misses.
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
