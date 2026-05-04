<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;

/**
 * Implémentation Eloquent des écritures Vehicle.
 *
 * Ne porte plus la transaction sur Vehicle + ses caractéristiques fiscales
 * - c'est désormais le rôle de {@see CreateVehicleAction}
 * qui orchestre les deux repositories sous une `DB::transaction`.
 */
final class VehicleWriteRepository implements VehicleWriteRepositoryInterface
{
    public function create(StoreVehicleData $data): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $data->licensePlate,
            'brand' => $data->brand,
            'model' => $data->model,
            'vin' => $data->vin,
            'color' => $data->color,
            'first_french_registration_date' => $data->firstFrenchRegistrationDate,
            'first_origin_registration_date' => $data->firstOriginRegistrationDate,
            'first_economic_use_date' => $data->firstEconomicUseDate,
            'acquisition_date' => $data->acquisitionDate,
            'current_status' => VehicleStatus::Active,
            'mileage_current' => $data->mileageCurrent,
            'notes' => $data->notes,
        ]);
    }

    public function update(int $vehicleId, UpdateVehicleData $data): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $vehicle->update([
            'license_plate' => $data->licensePlate,
            'brand' => $data->brand,
            'model' => $data->model,
            'vin' => $data->vin,
            'color' => $data->color,
            'first_french_registration_date' => $data->firstFrenchRegistrationDate,
            'first_origin_registration_date' => $data->firstOriginRegistrationDate,
            'first_economic_use_date' => $data->firstEconomicUseDate,
            'acquisition_date' => $data->acquisitionDate,
            'mileage_current' => $data->mileageCurrent,
            'notes' => $data->notes,
        ]);

        return $vehicle->fresh();
    }

    public function markAsExited(int $vehicleId, ExitVehicleData $data): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $vehicle->update([
            'exit_date' => $data->exitDate,
            'exit_reason' => $data->exitReason,
            'current_status' => self::statusForExitReason($data->exitReason),
        ]);

        return $vehicle->fresh();
    }

    public function markAsActive(int $vehicleId): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $vehicle->update([
            'exit_date' => null,
            'exit_reason' => null,
            'current_status' => VehicleStatus::Active,
        ]);

        return $vehicle->fresh();
    }

    /**
     * Mappe un motif de sortie vers le `current_status` cohérent.
     * Cf. ADR-0018 § 3 - asymétrie acceptée : Transferred et
     * StolenUnrecovered → Other.
     */
    private static function statusForExitReason(VehicleExitReason $reason): VehicleStatus
    {
        return match ($reason) {
            VehicleExitReason::Sold => VehicleStatus::Sold,
            VehicleExitReason::Destroyed => VehicleStatus::Destroyed,
            VehicleExitReason::Transferred,
            VehicleExitReason::StolenUnrecovered,
            VehicleExitReason::Other => VehicleStatus::Other,
        };
    }
}
