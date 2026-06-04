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

    public function findById(int $id): ?VehicleControlOverride;
}
