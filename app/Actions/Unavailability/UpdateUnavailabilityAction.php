<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Models\Unavailability;

/**
 * Updates a vehicle unavailability. Recomputes `has_fiscal_impact`
 * from the (possibly new) type. Symmetric to
 * {@see CreateUnavailabilityAction} regarding ADR-0019.
 */
final readonly class UpdateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id, UpdateUnavailabilityData $data): Unavailability
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
