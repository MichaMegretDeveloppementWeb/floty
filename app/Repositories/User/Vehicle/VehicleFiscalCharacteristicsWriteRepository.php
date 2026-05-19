<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreFiscalCharacteristicsData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\PollutantCategory;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

/**
 * Eloquent implementation of fiscal history writes.
 *
 * `pollutant_category` is never user-input: it is derived on every
 * write by {@see PollutantCategory::derive()} from the canonical
 * fields (energy source, Euro standard, underlying combustion engine
 * type). The DB stays consistent with the same cascade applied by the
 * fiscal computation (R-2024-013).
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
            'underlying_combustion_engine_type' => $data->underlyingCombustionEngineType,
            'euro_standard' => $data->euroStandard,
            'pollutant_category' => PollutantCategory::derive(
                $data->energySource,
                $data->euroStandard,
                $data->underlyingCombustionEngineType,
            ),
            'homologation_method' => $data->homologationMethod,
            'co2_wltp' => $data->co2Wltp,
            'co2_nedc' => $data->co2Nedc,
            'accepts_e85' => $data->acceptsE85,
            'taxable_horsepower' => $data->taxableHorsepower,
            'kerb_mass' => $data->kerbMass,
            'handicap_access' => $data->handicapAccess,
            'm1_special_use' => $data->m1SpecialUse,
            'n1_passenger_transport' => $data->n1PassengerTransport,
            'n1_removable_second_row_seat' => $data->n1RemovableSecondRowSeat,
            'n1_ski_lift_use' => $data->n1SkiLiftUse,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);
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
            'underlying_combustion_engine_type' => $data->underlyingCombustionEngineType,
            'euro_standard' => $data->euroStandard,
            'pollutant_category' => PollutantCategory::derive(
                $data->energySource,
                $data->euroStandard,
                $data->underlyingCombustionEngineType,
            ),
            'homologation_method' => $data->homologationMethod,
            'co2_wltp' => $data->co2Wltp,
            'co2_nedc' => $data->co2Nedc,
            'accepts_e85' => $data->acceptsE85,
            'taxable_horsepower' => $data->taxableHorsepower,
            'kerb_mass' => $data->kerbMass,
            'handicap_access' => $data->handicapAccess,
            'm1_special_use' => $data->m1SpecialUse,
            'n1_passenger_transport' => $data->n1PassengerTransport,
            'n1_removable_second_row_seat' => $data->n1RemovableSecondRowSeat,
            'n1_ski_lift_use' => $data->n1SkiLiftUse,
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
            'underlying_combustion_engine_type' => $data->underlyingCombustionEngineType,
            'euro_standard' => $data->euroStandard,
            'pollutant_category' => PollutantCategory::derive(
                $data->energySource,
                $data->euroStandard,
                $data->underlyingCombustionEngineType,
            ),
            'homologation_method' => $data->homologationMethod,
            'co2_wltp' => $data->co2Wltp,
            'co2_nedc' => $data->co2Nedc,
            'accepts_e85' => $data->acceptsE85,
            'taxable_horsepower' => $data->taxableHorsepower,
            'kerb_mass' => $data->kerbMass,
            'handicap_access' => $data->handicapAccess,
            'm1_special_use' => $data->m1SpecialUse,
            'n1_passenger_transport' => $data->n1PassengerTransport,
            'n1_removable_second_row_seat' => $data->n1RemovableSecondRowSeat,
            'n1_ski_lift_use' => $data->n1SkiLiftUse,
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

    public function createFromBoundsAndFields(
        int $vehicleId,
        StoreFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        return VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicleId,
            'effective_from' => $data->effectiveFrom,
            'effective_to' => $data->effectiveTo,
            'reception_category' => $data->receptionCategory,
            'vehicle_user_type' => $data->vehicleUserType,
            'body_type' => $data->bodyType,
            'seats_count' => $data->seatsCount,
            'energy_source' => $data->energySource,
            'underlying_combustion_engine_type' => $data->underlyingCombustionEngineType,
            'euro_standard' => $data->euroStandard,
            'pollutant_category' => PollutantCategory::derive(
                $data->energySource,
                $data->euroStandard,
                $data->underlyingCombustionEngineType,
            ),
            'homologation_method' => $data->homologationMethod,
            'co2_wltp' => $data->co2Wltp,
            'co2_nedc' => $data->co2Nedc,
            'accepts_e85' => $data->acceptsE85,
            'taxable_horsepower' => $data->taxableHorsepower,
            'kerb_mass' => $data->kerbMass,
            'handicap_access' => $data->handicapAccess,
            'm1_special_use' => $data->m1SpecialUse,
            'n1_passenger_transport' => $data->n1PassengerTransport,
            'n1_removable_second_row_seat' => $data->n1RemovableSecondRowSeat,
            'n1_ski_lift_use' => $data->n1SkiLiftUse,
            'change_reason' => $data->changeReason,
            'change_note' => $data->changeNote,
        ]);
    }
}
