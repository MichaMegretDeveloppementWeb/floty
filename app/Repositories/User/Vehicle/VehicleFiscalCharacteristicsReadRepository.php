<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

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

    public function findLastVersionStrictlyBefore(
        int $vehicleId,
        DateTimeInterface $date,
    ): ?VehicleFiscalCharacteristics {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->where('effective_from', '<', $date)
            ->latest('effective_from')
            ->first();
    }

    public function findById(int $id): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::query()->findOrFail($id);
    }

    public function findAdjacent(
        VehicleFiscalCharacteristics $vfc,
        int $direction,
    ): ?VehicleFiscalCharacteristics {
        $query = VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vfc->vehicle_id)
            ->where('id', '!=', $vfc->id);

        if ($direction === -1) {
            return $query
                ->where('effective_from', '<', $vfc->effective_from)
                ->latest('effective_from')
                ->first();
        }

        return $query
            ->where('effective_from', '>', $vfc->effective_from)
            ->oldest('effective_from')
            ->first();
    }

    public function countForVehicle(int $vehicleId): int
    {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->count();
    }
}
