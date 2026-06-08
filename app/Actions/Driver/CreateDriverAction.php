<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\StoreDriverData;
use App\Models\Driver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a driver alongside their initial company membership.
 * Business rule: a driver always belongs to at least one company on
 * creation.
 */
final class CreateDriverAction
{
    public function __construct(
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(StoreDriverData $data): Driver
    {
        return DB::transaction(function () use ($data): Driver {
            $driver = $this->driverWriteRepo->create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'email' => $data->email,
            ]);

            $this->driverWriteRepo->attachCompany(
                driverId: $driver->id,
                companyId: $data->initialCompanyId,
                joinedAt: Carbon::parse($data->initialJoinedAt),
            );

            return $driver->fresh() ?? $driver;
        });
    }
}
