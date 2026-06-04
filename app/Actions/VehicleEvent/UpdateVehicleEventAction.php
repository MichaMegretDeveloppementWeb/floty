<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
use App\Models\VehicleEvent;

/**
 * Updates a vehicle unavailability. Recomputes `has_fiscal_impact`
 * from the (possibly new) type. Symmetric to
 * {@see CreateVehicleEventAction} regarding ADR-0019.
 */
final readonly class UpdateVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id, UpdateVehicleEventData $data): VehicleEvent
    {
        return $this->repository->update($id, [
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
