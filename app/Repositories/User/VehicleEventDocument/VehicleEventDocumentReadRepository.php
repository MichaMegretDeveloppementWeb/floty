<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEventDocument;

use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentReadRepositoryInterface;
use App\Models\VehicleEventDocument;
use Illuminate\Database\Eloquent\Collection;

final class VehicleEventDocumentReadRepository implements VehicleEventDocumentReadRepositoryInterface
{
    public function findById(int $id): ?VehicleEventDocument
    {
        return VehicleEventDocument::query()->find($id);
    }

    public function listForVehicleEvent(int $vehicleEventId): Collection
    {
        return VehicleEventDocument::query()
            ->where('vehicle_event_id', $vehicleEventId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function countForVehicleEvent(int $vehicleEventId): int
    {
        return VehicleEventDocument::query()
            ->where('vehicle_event_id', $vehicleEventId)
            ->count();
    }
}
