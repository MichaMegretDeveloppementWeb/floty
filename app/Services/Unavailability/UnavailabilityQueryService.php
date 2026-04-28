<?php

declare(strict_types=1);

namespace App\Services\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Models\Unavailability;

/**
 * Composition des indisponibilités vers les DTOs exposés au front.
 *
 * Le repo retourne des Models bruts ; ce service compose les DTOs
 * (calcul du `daysCount`, etc.).
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
