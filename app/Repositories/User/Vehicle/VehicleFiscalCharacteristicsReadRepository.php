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
        // Si la relation est préchargée (eager load via `with(...)`),
        // on travaille sur la collection en mémoire pour éviter une
        // nouvelle requête SQL inutile. Évite le N+1 sur l'Index Flotte
        // qui itère sur tous les véhicules avec leurs VFC déjà
        // eager-loadées par {@see VehicleReadRepository::findAllForFleetView}.
        if ($vehicle->relationLoaded('fiscalCharacteristics')) {
            return $vehicle->fiscalCharacteristics
                ->where('effective_to', null)
                ->sortByDesc('effective_from')
                ->first();
        }

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

    public function findOthersForVehicle(int $vehicleId, int $excludeId): array
    {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->where('id', '!=', $excludeId)
            ->orderBy('effective_from')
            ->get()
            ->all();
    }
}
