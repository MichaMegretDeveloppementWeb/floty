<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
use App\Models\VehicleEvent;
use App\Services\VehicleEvent\EventNatureFiscalResolver;
use App\Support\VehicleEvent\EventCategoryList;

/**
 * Updates a vehicle event. Recomputes `has_fiscal_impact` from the (possibly
 * new) natures and re-forces the unavailability flag when reductive.
 * Symmetric to {@see CreateVehicleEventAction} (ADR-0019).
 */
final readonly class UpdateVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
        private EventNatureFiscalResolver $fiscalResolver,
    ) {}

    public function execute(int $id, UpdateVehicleEventData $data): VehicleEvent
    {
        $categories = EventCategoryList::compose([], $data->categories ?? []);
        $hasFiscalImpact = $this->fiscalResolver->hasReductiveNature($categories);

        return $this->repository->update($id, [
            'title' => trim($data->title),
            'has_fiscal_impact' => $hasFiscalImpact,
            'implies_unavailability' => $hasFiscalImpact ? true : $data->impliesUnavailability,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
            'amount_cents' => $data->amountCents,
        ], $categories);
    }
}
