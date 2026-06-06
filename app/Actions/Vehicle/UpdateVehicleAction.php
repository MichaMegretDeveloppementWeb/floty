<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Exceptions\Vehicle\MissingNewVersionMetadataException;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\VehicleEvent\VehicleLifecycleEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Updates a vehicle from the Edit page. All writes happen inside a
 * single `DB::transaction`.
 *
 * Pipeline:
 *  1. Identity fields (plate, brand, model, vin, color, dates, mileage,
 *     notes) are always UPDATED in place on `vehicles`; no versioning.
 *  2. Fiscal characteristics: branch on `$data->createNewVersion`.
 *
 *     a. `createNewVersion = false` (default · in-place correction).
 *        The user corrects the current VFC; effective bounds and
 *        change reason are preserved. The change is retroactive on
 *        the whole effective period · suited to fixing a typo on a
 *        freshly created vehicle.
 *
 *     b. `createNewVersion = true` (explicit versioning).
 *        `effectiveFrom` and `changeReason` become mandatory; absent
 *        ones raise {@see MissingNewVersionMetadataException}.
 *        Retroactive cascade: any version whose `effective_from` is
 *        >= `effectiveFrom` is HARD-deleted (a ConfirmModal lists
 *        impacted versions UI-side). The previous version (if any)
 *        has its `effective_to` set to the day before the new
 *        version, and a new VFC is INSERTed with `effective_to =
 *        null`.
 *
 * If no fiscal field changed, the fiscal branch is skipped entirely
 * regardless of `createNewVersion`.
 *
 * Edits on a historical (non-current) VFC still go through the
 * Historique modal exclusively (see {@see
 * UpdateFiscalCharacteristicsAction}).
 */
final readonly class UpdateVehicleAction
{
    public function __construct(
        private VehicleReadRepositoryInterface $vehicles,
        private VehicleWriteRepositoryInterface $vehicleWriter,
        private VehicleFiscalCharacteristicsReadRepositoryInterface $fiscalReader,
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $fiscalWriter,
        private VehicleLifecycleEventRecorder $lifecycle,
    ) {}

    public function execute(int $vehicleId, UpdateVehicleData $data): Vehicle
    {
        return DB::transaction(function () use ($vehicleId, $data): Vehicle {
            $previousAcquisitionDate = $this->vehicles->findOrFail($vehicleId)->acquisition_date->toDateString();

            $vehicle = $this->vehicleWriter->update($vehicleId, $data);

            // Keep the « Entrée en flotte » marker in sync if the acquisition
            // date changed.
            if ($vehicle->acquisition_date->toDateString() !== $previousAcquisitionDate) {
                $this->lifecycle->recordAcquisition($vehicle);
            }

            $current = $this->loadCurrentVfc($vehicleId);

            if (! $this->hasFiscalChanges($current, $data)) {
                return $vehicle;
            }

            if ($data->createNewVersion) {
                $this->applyNewVersion($vehicleId, $data);
            } else {
                $this->fiscalWriter->updateFieldsOnly($current->id, $data);
            }

            return $vehicle;
        });
    }

    /**
     * Retroactive cascade, previous version closure, new VFC INSERT.
     * Pre-condition: a fiscal field actually changed.
     */
    private function applyNewVersion(int $vehicleId, UpdateVehicleData $data): void
    {
        if ($data->effectiveFrom === null || $data->changeReason === null) {
            throw MissingNewVersionMetadataException::make();
        }

        $effectiveFrom = CarbonImmutable::parse($data->effectiveFrom);

        $this->fiscalWriter->deleteVersionsFromDate($vehicleId, $effectiveFrom);

        $previous = $this->fiscalReader->findLastVersionStrictlyBefore(
            $vehicleId,
            $effectiveFrom,
        );

        if ($previous !== null) {
            $this->fiscalWriter->setEffectiveTo(
                $previous->id,
                $effectiveFrom->subDay(),
            );
        }

        $this->fiscalWriter->createNewVersion(
            vehicleId: $vehicleId,
            data: $data,
            effectiveFrom: $effectiveFrom,
            reason: $data->changeReason,
            note: $data->changeNote,
        );
    }

    /**
     * Loads the current VFC of the vehicle. Raises a LogicException if
     * none is found; a vehicle should always have at least its
     * `InitialCreation` row (see CreateVehicleAction).
     */
    private function loadCurrentVfc(int $vehicleId): VehicleFiscalCharacteristics
    {
        $vehicle = $this->vehicles->findOrFailWithFiscal($vehicleId);
        $current = $this->fiscalReader->findCurrentForVehicle($vehicle);

        if ($current === null) {
            throw new \LogicException(
                "Vehicle #{$vehicleId} has no current fiscal version.",
            );
        }

        return $current;
    }

    /**
     * Returns true when at least one fiscal field differs from the
     * current VFC. The pollutant category is not compared because it
     * is derived from the other fiscal inputs; any change in it
     * implies a change on one of those inputs already covered here.
     */
    private function hasFiscalChanges(
        VehicleFiscalCharacteristics $current,
        UpdateVehicleData $data,
    ): bool {
        return $current->reception_category !== $data->receptionCategory
            || $current->vehicle_user_type !== $data->vehicleUserType
            || $current->body_type !== $data->bodyType
            || $current->seats_count !== $data->seatsCount
            || $current->energy_source !== $data->energySource
            || $current->underlying_combustion_engine_type !== $data->underlyingCombustionEngineType
            || $current->euro_standard !== $data->euroStandard
            || $current->homologation_method !== $data->homologationMethod
            || $current->co2_wltp !== $data->co2Wltp
            || $current->co2_nedc !== $data->co2Nedc
            || $current->accepts_e85 !== $data->acceptsE85
            || $current->taxable_horsepower !== $data->taxableHorsepower
            || $current->kerb_mass !== $data->kerbMass
            || $current->handicap_access !== $data->handicapAccess
            || $current->m1_special_use !== $data->m1SpecialUse
            || $current->n1_passenger_transport !== $data->n1PassengerTransport
            || $current->n1_removable_second_row_seat !== $data->n1RemovableSecondRowSeat
            || $current->n1_ski_lift_use !== $data->n1SkiLiftUse;
    }
}
