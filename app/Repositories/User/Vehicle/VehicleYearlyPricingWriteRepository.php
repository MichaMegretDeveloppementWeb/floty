<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\VehicleYearlyPricing;

/**
 * Implémentation Eloquent du contrat d'écriture des tarifs véhicule × année.
 *
 * Repository sans état (singleton via {@see App\Providers\RepositoryServiceProvider}).
 */
final class VehicleYearlyPricingWriteRepository implements VehicleYearlyPricingWriteRepositoryInterface
{
    public function upsert(int $vehicleId, VehicleYearlyPricingData $data): VehicleYearlyPricing
    {
        // updateOrCreate s'appuie sur la contrainte UNIQUE(vehicle_id, year)
        // pour garantir l'idempotence : appeler 2× avec les mêmes
        // paramètres produit la même ligne.
        return VehicleYearlyPricing::updateOrCreate(
            [
                'vehicle_id' => $vehicleId,
                'year' => $data->year,
            ],
            [
                'daily_rate_cents' => $data->dailyRateCents,
                'weekly_rate_cents' => $data->weeklyRateCents,
                'monthly_rate_cents' => $data->monthlyRateCents,
            ],
        );
    }

    public function deleteForVehicleAndYear(int $vehicleId, int $year): bool
    {
        $deleted = VehicleYearlyPricing::query()
            ->where('vehicle_id', $vehicleId)
            ->where('year', $year)
            ->delete();

        return $deleted > 0;
    }
}
