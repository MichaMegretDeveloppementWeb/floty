<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Models\VehicleEvent;

final class VehicleEventWriteRepository implements VehicleEventWriteRepositoryInterface
{
    public function create(array $attributes): VehicleEvent
    {
        return VehicleEvent::create($attributes);
    }

    public function update(int $id, array $attributes): VehicleEvent
    {
        $vehicleEvent = VehicleEvent::query()->findOrFail($id);
        $vehicleEvent->update($attributes);

        return $vehicleEvent->fresh();
    }

    public function softDelete(int $id): void
    {
        VehicleEvent::query()->findOrFail($id)->delete();
    }
}
