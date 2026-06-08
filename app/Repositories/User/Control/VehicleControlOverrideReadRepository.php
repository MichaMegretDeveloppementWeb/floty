<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Models\VehicleControlOverride;
use Illuminate\Database\Eloquent\Collection;

final class VehicleControlOverrideReadRepository implements VehicleControlOverrideReadRepositoryInterface
{
    /**
     * @return Collection<int, VehicleControlOverride>
     */
    public function findForVehicle(int $vehicleId): Collection
    {
        return VehicleControlOverride::query()
            ->with('recipientDeltas')
            ->where('vehicle_id', $vehicleId)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return Collection<int, VehicleControlOverride>
     */
    public function findForVehicles(array $vehicleIds): Collection
    {
        if ($vehicleIds === []) {
            return new Collection;
        }

        return VehicleControlOverride::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id): ?VehicleControlOverride
    {
        return VehicleControlOverride::query()->find($id);
    }
}
