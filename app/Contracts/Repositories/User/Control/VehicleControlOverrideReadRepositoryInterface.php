<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Models\VehicleControlOverride;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads of per-vehicle control overrides / vehicle-specific controls (Chantier B / B2).
 */
interface VehicleControlOverrideReadRepositoryInterface
{
    /**
     * All non-deleted override rows for a vehicle, with their recipient deltas.
     *
     * @return Collection<int, VehicleControlOverride>
     */
    public function findForVehicle(int $vehicleId): Collection;

    /**
     * All non-deleted override rows for many vehicles at once (batch variant of
     * {@see findForVehicle()} for fleet-wide scans), WITHOUT recipient deltas:
     * the schedule scan only needs the échéance recipe, not the recipient
     * cascade. One query total.
     *
     * @param  list<int>  $vehicleIds
     * @return Collection<int, VehicleControlOverride>
     */
    public function findForVehicles(array $vehicleIds): Collection;

    public function findById(int $id): ?VehicleControlOverride;
}
