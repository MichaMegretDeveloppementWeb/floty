<?php

declare(strict_types=1);

namespace App\Services\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\VehicleEvent\PaginatedVehicleEventListData;
use App\Data\User\VehicleEvent\VehicleEventData;
use App\Data\User\VehicleEvent\VehicleEventIndexQueryData;
use App\Data\User\VehicleEvent\VehicleEventListItemData;
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

    /**
     * Global vehicle-events index (all vehicles), slim + paginated.
     */
    public function listForGlobalIndex(VehicleEventIndexQueryData $query): PaginatedVehicleEventListData
    {
        $paginator = $this->repository->paginateForIndex($query);

        /** @var list<VehicleEvent> $events */
        $events = $paginator->items();

        $items = array_map(
            static fn (VehicleEvent $e): VehicleEventListItemData => VehicleEventListItemData::fromModel($e),
            $events,
        );

        return new PaginatedVehicleEventListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }
}
