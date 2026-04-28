<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

/**
 * Implémentation Eloquent des écritures de l'historique fiscal.
 */
final class VehicleFiscalCharacteristicsWriteRepository implements VehicleFiscalCharacteristicsWriteRepositoryInterface
{
    public function createInitialVersion(
        int $vehicleId,
        StoreVehicleData $data,
        DateTimeInterface $effectiveFrom,
    ): VehicleFiscalCharacteristics {
        return VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicleId,
            'effective_from' => $effectiveFrom,
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
    }
}
