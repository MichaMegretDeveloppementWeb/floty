<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;

/**
 * Implémentation Eloquent des écritures Vehicle.
 *
 * Ne porte plus la transaction sur Vehicle + ses caractéristiques fiscales
 * — c'est désormais le rôle de {@see CreateVehicleAction}
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
}
