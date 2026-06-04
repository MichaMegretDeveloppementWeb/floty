<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;

/**
 * Creates a vehicle unavailability.
 *
 * `has_fiscal_impact` is derived from the type via
 * {@see VehicleEventType::isFiscallyReductive()}; never carried by the
 * user payload. A SQL CHECK enforces the same coherence in DB.
 *
 * No overlap constraint with active contracts (ADR-0019): an unavailability
 * may be recorded over an existing contract range; the intersection is
 * handled at calculation time by R-2024-008 for the fiscally-reductive
 * types.
 */
final readonly class CreateVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
    ) {}

    public function execute(StoreVehicleEventData $data): VehicleEvent
    {
        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
