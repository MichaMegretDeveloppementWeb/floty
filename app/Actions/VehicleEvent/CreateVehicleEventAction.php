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
        // Normalise the invariants server-side rather than trust the client:
        // known types derive title/category (kept null) and always imply
        // unavailability; only the `other` type carries custom identity and
        // an opt-in flag. `has_fiscal_impact` stays enum-driven (Other = false).
        $isCustom = $data->type === VehicleEventType::Other;

        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
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
