<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\UpdateDriverData;
use App\Models\Driver;

final class UpdateDriverAction
{
    public function __construct(
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(Driver $driver, UpdateDriverData $data): Driver
    {
        return $this->driverWriteRepo->update($driver, [
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
        ]);
    }
}
