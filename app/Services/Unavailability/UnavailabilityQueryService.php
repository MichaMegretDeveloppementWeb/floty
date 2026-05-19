<?php

declare(strict_types=1);

namespace App\Services\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Models\Unavailability;

/**
 * Query service composing unavailability DTOs for the frontend.
 */
final readonly class UnavailabilityQueryService
{
    public function __construct(
        private UnavailabilityReadRepositoryInterface $repository,
    ) {}

    /**
     * @return list<UnavailabilityData>
     */
    public function findForVehicle(int $vehicleId): array
    {
        return $this->repository->findForVehicle($vehicleId)
            ->map(static fn (Unavailability $u): UnavailabilityData => UnavailabilityData::fromModel($u))
            ->values()
            ->all();
    }
}
