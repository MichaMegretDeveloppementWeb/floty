<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;

/**
 * Implémentation Eloquent des lectures sur l'historique fiscal d'un
 * véhicule.
 */
final class VehicleFiscalCharacteristicsReadRepository implements VehicleFiscalCharacteristicsReadRepositoryInterface
{
    public function findCurrentForVehicle(Vehicle $vehicle): ?VehicleFiscalCharacteristics
    {
        return $vehicle->fiscalCharacteristics()
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();
    }
}
