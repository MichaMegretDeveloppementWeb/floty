<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Exceptions\Driver\DriverCompanyMembershipBlockedException;
use App\Exceptions\Driver\DriverMembershipNotFoundException;

/**
 * Supprime une membership Driver↔Company. Refusé si elle a au moins
 * un contrat associé (utiliser LeaveDriverCompanyMembershipAction à la place).
 */
final class DetachDriverCompanyMembershipAction
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
        private readonly ContractReadRepositoryInterface $contractReadRepo,
    ) {}

    public function execute(int $pivotId): void
    {
        $pivot = $this->driverReadRepo->findMembershipById($pivotId);
        if ($pivot === null) {
            throw DriverMembershipNotFoundException::forPivotId($pivotId);
        }

        $contractsCount = $this->contractReadRepo->countForDriverInCompany(
            $pivot->driver_id,
            $pivot->company_id,
        );

        if ($contractsCount > 0) {
            throw DriverCompanyMembershipBlockedException::hasContracts($pivotId, $contractsCount);
        }

        $this->driverWriteRepo->deleteMembership($pivotId);
    }
}
