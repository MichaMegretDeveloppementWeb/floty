<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;

/**
 * Soft-deletes a vehicle unavailability.
 */
final readonly class DeleteUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        $this->repository->softDelete($id);
    }
}
