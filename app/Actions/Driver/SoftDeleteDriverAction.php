<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Exceptions\Driver\DriverDeletionBlockedException;
use App\Models\Driver;

/**
 * Suppression soft d'un driver. Refusée si au moins 1 contrat le référence
 * (préservation cohérence historique).
 */
final class SoftDeleteDriverAction
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(Driver $driver): void
    {
        $contractsCount = $this->driverReadRepo->countContractsForDriver($driver->id);

        if ($contractsCount > 0) {
            throw DriverDeletionBlockedException::hasContracts($driver->id, $contractsCount);
        }

        $this->driverWriteRepo->softDelete($driver);
    }
}
