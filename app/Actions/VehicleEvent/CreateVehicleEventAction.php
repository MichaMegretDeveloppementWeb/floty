<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Models\VehicleEvent;
use App\Services\VehicleEvent\EventNatureFiscalResolver;
use App\Support\VehicleEvent\EventCategoryList;

/**
 * Creates a vehicle event (refonte type → nature).
 *
 * `has_fiscal_impact` is never carried by the user payload: it is derived
 * from the natures, true as soon as one of them belongs to the frozen
 * reductive block of the catalogue ({@see EventNatureFiscalResolver}). A
 * reductive event always implies an unavailability, so the user flag is
 * overridden in that case (SQL CHECK as backstop).
 *
 * No overlap constraint with active contracts (ADR-0019): an event may be
 * recorded over an existing contract range; the intersection is handled at
 * calculation time by R-20XX-008 for the fiscally-reductive events.
 */
final readonly class CreateVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
        private EventNatureFiscalResolver $fiscalResolver,
    ) {}

    public function execute(StoreVehicleEventData $data): VehicleEvent
    {
        $categories = EventCategoryList::compose([], $data->categories ?? []);
        $details = EventCategoryList::compose([], $data->details ?? []);
        $hasFiscalImpact = $this->fiscalResolver->hasReductiveNature($categories);

        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
            'title' => trim($data->title),
            'has_fiscal_impact' => $hasFiscalImpact,
            'implies_unavailability' => $hasFiscalImpact ? true : $data->impliesUnavailability,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
            'garage' => $this->nullableTrimmed($data->garage),
            'postal_code' => $this->nullableTrimmed($data->postalCode),
            'amount_cents' => $data->amountCents,
        ], $categories, $details);
    }

    /**
     * Trimmed value, or null when blank (a spaces-only garage must not feed
     * the autocomplete).
     */
    private function nullableTrimmed(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
