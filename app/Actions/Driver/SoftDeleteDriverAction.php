<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Exceptions\Driver\DriverDeletionBlockedException;
use App\Models\Driver;

/**
 * Soft-deletes a driver. Refused if at least one contract references
 * them (historical integrity).
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
