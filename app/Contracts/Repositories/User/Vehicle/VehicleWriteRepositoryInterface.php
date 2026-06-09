<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Models\Vehicle;

/**
 * Writes on the Vehicle entity proper.
 *
 * Does not carry the "Vehicle + fiscal characteristics" transaction ·
 * multi-entity orchestration is the responsibility of
 * {@see CreateVehicleAction} (ADR-0013 R3: any chaining of multiple
 * services/repositories around a business decision belongs to the
 * Action layer).
 */
interface VehicleWriteRepositoryInterface
{
    /**
     * Persists a vehicle without touching its fiscal history.
     */
    public function create(StoreVehicleData $data): Vehicle;

    /**
     * Updates the identity fields of a vehicle only (license_plate,
     * brand, model, vin, color, registration dates, acquisition,
     * mileage, notes). Does not touch the fiscal history · that is
     * handled separately by
     * {@see VehicleFiscalCharacteristicsWriteRepositoryInterface} under
     * Action orchestration.
     */
    public function update(int $vehicleId, UpdateVehicleData $data): Vehicle;

    /**
     * Marks a vehicle as exited from the fleet: sets `exit_date`,
     * `exit_reason`, and updates `current_status` consistently
     * (sold → Sold, destroyed → Destroyed, others → Other ·
     * ADR-0018 § 3).
     *
     * Validation of overflowing conflicts (contracts/unavailabilities)
     * is the responsibility of the calling Action via
     * {@see App\Services\Vehicle\VehicleExitImpactComputer}.
     */
    public function markAsExited(int $vehicleId, ExitVehicleData $data): Vehicle;

    /**
     * Reactivates a previously exited vehicle: resets `exit_date`,
     * `exit_reason` to NULL and `current_status` to Active.
     */
    public function markAsActive(int $vehicleId): Vehicle;

    /**
     * Persists the materialised `controls_due_from` for a batch of vehicles in
     * one query (id => `Y-m-d`|null). Bypasses model events on purpose (it is
     * the recompute output).
     *
     * @param  array<int, string|null>  $dueFromByVehicleId
     */
    public function updateControlsDueFrom(array $dueFromByVehicleId): void;
}
