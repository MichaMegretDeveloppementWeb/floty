<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;

/**
 * Soft-deletes a vehicle unavailability.
 */
final readonly class DeleteVehicleEventAction
{
    public function __construct(
        private VehicleEventWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        $this->repository->softDelete($id);
    }
}
