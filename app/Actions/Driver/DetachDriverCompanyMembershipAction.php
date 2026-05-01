<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Exceptions\Driver\DriverCompanyMembershipBlockedException;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une membership Driver↔Company. Refusé si elle a au moins
 * un contrat associé (utiliser LeaveDriverCompanyMembershipAction à la place).
 */
final class DetachDriverCompanyMembershipAction
{
    public function __construct(
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(int $pivotId): void
    {
        $pivot = DB::table('driver_company')->where('id', $pivotId)->first();
        if ($pivot === null) {
            return;
        }

        $contractsCount = Contract::query()
            ->where('driver_id', $pivot->driver_id)
            ->where('company_id', $pivot->company_id)
            ->count();

        if ($contractsCount > 0) {
            throw DriverCompanyMembershipBlockedException::hasContracts($pivotId, $contractsCount);
        }

        $this->driverWriteRepo->deleteMembership($pivotId);
    }
}
