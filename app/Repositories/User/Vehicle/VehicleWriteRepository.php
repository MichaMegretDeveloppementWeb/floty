<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des écritures Vehicle.
 *
 * La transaction atomique vit ici (R4 : repositories portent les
 * transactions liées à leurs entités).
 */
final class VehicleWriteRepository implements VehicleWriteRepositoryInterface
{
    public function createWithInitialFiscalCharacteristics(StoreVehicleData $data): Vehicle
    {
        return DB::transaction(function () use ($data): Vehicle {
            $vehicle = Vehicle::create([
                'license_plate' => mb_strtoupper($data->licensePlate),
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

            VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => $vehicle->acquisition_date,
                'effective_to' => null,
                'reception_category' => $data->receptionCategory,
                'vehicle_user_type' => $data->vehicleUserType,
                'body_type' => $data->bodyType,
                'seats_count' => $data->seatsCount,
                'energy_source' => $data->energySource,
                'euro_standard' => $data->euroStandard,
                'pollutant_category' => $data->pollutantCategory,
                'homologation_method' => $data->homologationMethod,
                'co2_wltp' => $data->co2Wltp,
                'co2_nedc' => $data->co2Nedc,
                'taxable_horsepower' => $data->taxableHorsepower,
                'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            ]);

            return $vehicle;
        });
    }
}
