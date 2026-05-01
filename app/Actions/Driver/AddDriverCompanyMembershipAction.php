<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\AddDriverCompanyMembershipData;
use App\Models\Driver;
use Illuminate\Support\Carbon;

final class AddDriverCompanyMembershipAction
{
    public function __construct(
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(Driver $driver, AddDriverCompanyMembershipData $data): void
    {
        $this->driverWriteRepo->attachCompany(
            driverId: $driver->id,
            companyId: $data->companyId,
            joinedAt: Carbon::parse($data->joinedAt),
        );
    }
}
