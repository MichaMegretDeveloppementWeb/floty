<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\StoreDriverData;
use App\Models\Driver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crée un driver avec sa membership initiale dans une entreprise.
 * Cf. Q-NEW2 : au moins 1 entreprise obligatoire à la création.
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
