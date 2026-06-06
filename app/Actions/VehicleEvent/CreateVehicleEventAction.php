<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;
use App\Support\VehicleEvent\EventCategoryList;

/**
 * Creates a vehicle event.
 *
 * `has_fiscal_impact` is derived from the type via
 * {@see VehicleEventType::isFiscallyReductive()}; never carried by the
 * user payload. A SQL CHECK enforces the same coherence in DB.
 *
 * Categories (up to 5) are composed server-side: a known type prepends its
 * fixed default ({@see VehicleEventType::defaultCategory()}), then the user's
 * additions, deduped and capped ({@see EventCategoryList}). The `other` type
 * carries only the user-supplied ones.
 *
 * No overlap constraint with active contracts (ADR-0019): an event may be
 * recorded over an existing contract range; the intersection is handled at
 * calculation time by R-2024-008 for the fiscally-reductive types.
 */
final readonly class CreateVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
    ) {}

    public function execute(StoreVehicleEventData $data): VehicleEvent
    {
        // Normalise the invariants server-side rather than trust the client:
        // known types derive their title (kept null) and always imply
        // unavailability; only the `other` type carries custom identity and
        // an opt-in flag. `has_fiscal_impact` stays enum-driven (Other = false).
        $isCustom = $data->type === VehicleEventType::Other;

        $default = $data->type->defaultCategory();
        $categories = EventCategoryList::compose(
            $default === null ? [] : [$default],
            $data->categories ?? [],
        );

        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
            'type' => $data->type,
            'title' => $isCustom ? $data->title : null,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'implies_unavailability' => $isCustom ? $data->impliesUnavailability : true,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ], $categories);
    }
}
