<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateVehicleData;
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

    public function updateInPlace(
        int $fiscalId,
        UpdateVehicleData $data,
    ): VehicleFiscalCharacteristics {
        $vfc = VehicleFiscalCharacteristics::findOrFail($fiscalId);

        $vfc->update([
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
            'change_reason' => FiscalCharacteristicsChangeReason::InputCorrection,
        ]);

        return $vfc->fresh();
    }

    public function createNewVersion(
        int $vehicleId,
        UpdateVehicleData $data,
        DateTimeInterface $effectiveFrom,
        FiscalCharacteristicsChangeReason $reason,
        ?string $note,
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
            'change_reason' => $reason,
            'change_note' => $note,
        ]);
    }

    public function setEffectiveTo(
        int $fiscalId,
        ?DateTimeInterface $effectiveTo,
    ): VehicleFiscalCharacteristics {
        $vfc = VehicleFiscalCharacteristics::findOrFail($fiscalId);

        $vfc->update(['effective_to' => $effectiveTo]);

        return $vfc->fresh();
    }

    public function deleteVersionsFromDate(
        int $vehicleId,
        DateTimeInterface $date,
    ): int {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->where('effective_from', '>=', $date)
            ->delete();
    }

    public function setEffectiveFrom(
        int $fiscalId,
        DateTimeInterface $effectiveFrom,
    ): VehicleFiscalCharacteristics {
        $vfc = VehicleFiscalCharacteristics::findOrFail($fiscalId);

        $vfc->update(['effective_from' => $effectiveFrom]);

        return $vfc->fresh();
    }

    public function updateBoundsAndFields(
        int $fiscalId,
        UpdateFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        $vfc = VehicleFiscalCharacteristics::findOrFail($fiscalId);

        $vfc->update([
            'effective_from' => $data->effectiveFrom,
            'effective_to' => $data->effectiveTo,
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
            'change_reason' => $data->changeReason,
            'change_note' => $data->changeNote,
        ]);

        return $vfc->fresh();
    }

    public function deleteOne(int $fiscalId): void
    {
        VehicleFiscalCharacteristics::query()
            ->where('id', $fiscalId)
            ->delete();
    }
}
