<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\StoreFiscalCharacteristicsData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

/**
 * Writes on a vehicle's fiscal history.
 *
 * Separated from {@see VehicleWriteRepositoryInterface}: the lifecycle
 * of a fiscal period (initial creation, new version, correction of an
 * existing version, retroactive cascade) is driven by domain Actions
 * (ADR-0013 R3 · multi-entity orchestration belongs to the Action
 * layer, not the repository).
 */
interface VehicleFiscalCharacteristicsWriteRepositoryInterface
{
    /**
     * Creates the first fiscal period of a freshly inserted vehicle
     * (`change_reason = InitialCreation`, `effective_to = null`).
     *
     * Business invariants (homologation, WLTP/CO₂ consistency, etc.)
     * are assumed validated upstream (FormRequest + Action).
     */
    public function createInitialVersion(
        int $vehicleId,
        StoreVehicleData $data,
        DateTimeInterface $effectiveFrom,
    ): VehicleFiscalCharacteristics;

    /**
     * Creates a new history row with the provided characteristics. Used
     * by `UpdateVehicleAction` after closing/deleting adjacent versions.
     */
    public function createNewVersion(
        int $vehicleId,
        UpdateVehicleData $data,
        DateTimeInterface $effectiveFrom,
        FiscalCharacteristicsChangeReason $reason,
        ?string $note,
    ): VehicleFiscalCharacteristics;

    /**
     * Updates the `effective_to` bound of an existing version. Used to
     * close a current version when creating a new one, or to restore
     * `null` when the current version is deleted.
     */
    public function setEffectiveTo(
        int $fiscalId,
        ?DateTimeInterface $effectiveTo,
    ): VehicleFiscalCharacteristics;

    /**
     * Physically deletes (HARD DELETE) all VFC versions of a vehicle
     * whose `effective_from` is on or after the given date. Used by the
     * retroactive cascade: when a new version is created in the past,
     * all later versions no longer make sense and are wiped.
     *
     * @return int Number of deleted rows
     */
    public function deleteVersionsFromDate(
        int $vehicleId,
        DateTimeInterface $date,
    ): int;

    /**
     * Updates the `effective_from` bound of an existing version. Used
     * to adjust the next VFC when its predecessor is deleted and the
     * user chose `ExtendNext`.
     */
    public function setEffectiveFrom(
        int $fiscalId,
        DateTimeInterface $effectiveFrom,
    ): VehicleFiscalCharacteristics;

    /**
     * Full UPDATE (bounds + fiscal fields + reason/note) of a historical
     * VFC from the History modal. Inter-version invariants are validated
     * upstream by the Action.
     */
    public function updateBoundsAndFields(
        int $fiscalId,
        UpdateFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics;

    /**
     * Inserts a new VFC added from the History modal ("+ Add entry"
     * button). Adjustments on neighbours (Delete/Adjust) are handled by
     * the calling Action upstream · this writer only performs the raw
     * insert.
     */
    public function createFromBoundsAndFields(
        int $vehicleId,
        StoreFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics;

    /**
     * Physically deletes (HARD DELETE) a single VFC. Backfilling any
     * gap left behind is the responsibility of the calling Action.
     */
    public function deleteOne(int $fiscalId): void;
}
