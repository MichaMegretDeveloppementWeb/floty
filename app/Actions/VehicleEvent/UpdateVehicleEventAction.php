<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;

/**
 * Updates a vehicle event. Recomputes `has_fiscal_impact` and re-normalises
 * the custom identity / unavailability flag from the (possibly new) type.
 * Symmetric to {@see CreateVehicleEventAction} regarding ADR-0019.
 */
final readonly class UpdateVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id, UpdateVehicleEventData $data): VehicleEvent
    {
        $isCustom = $data->type === VehicleEventType::Other;

        return $this->repository->update($id, [
            'type' => $data->type,
            'title' => $isCustom ? $data->title : null,
            'category' => $isCustom ? $data->category : null,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'implies_unavailability' => $isCustom ? $data->impliesUnavailability : true,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
