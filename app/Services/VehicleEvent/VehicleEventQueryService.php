<?php

declare(strict_types=1);

namespace App\Services\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\User\VehicleEvent\VehicleEventData;
use App\Models\VehicleEvent;

/**
 * Query service composing unavailability DTOs for the frontend.
 */
final readonly class VehicleEventQueryService
{
    public function __construct(
        private VehicleEventReadRepositoryInterface $repository,
    ) {}

    /**
     * @return list<VehicleEventData>
     */
    public function findForVehicle(int $vehicleId): array
    {
        return $this->repository->findForVehicle($vehicleId)
            ->map(static fn (VehicleEvent $u): VehicleEventData => VehicleEventData::fromModel($u))
            ->values()
            ->all();
    }
}
