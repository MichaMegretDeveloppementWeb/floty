<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Models\VehicleYearlyPricing;

/**
 * Implémentation Eloquent du contrat de lecture des tarifs véhicule × année.
 *
 * Repository sans état (singleton via {@see App\Providers\RepositoryServiceProvider}).
 */
final class VehicleYearlyPricingReadRepository implements VehicleYearlyPricingReadRepositoryInterface
{
    public function findForVehicleAndYear(int $vehicleId, int $year): ?VehicleYearlyPricing
    {
        return VehicleYearlyPricing::query()
            ->where('vehicle_id', $vehicleId)
            ->where('year', $year)
            ->first();
    }

    /**
     * @return list<VehicleYearlyPricing>
     */
    public function findAllForVehicle(int $vehicleId): array
    {
        return VehicleYearlyPricing::query()
            ->where('vehicle_id', $vehicleId)
            ->orderBy('year')
            ->get()
            ->all();
    }

    public function findForVehiclesAndYear(array $vehicleIds, int $year): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        return VehicleYearlyPricing::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('year', $year)
            ->get()
            ->keyBy('vehicle_id')
            ->all();
    }
}
