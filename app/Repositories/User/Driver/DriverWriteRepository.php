<?php

declare(strict_types=1);

namespace App\Repositories\User\Driver;

use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Models\Driver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des écritures Driver - slim conforme ADR-0013.
 */
final class DriverWriteRepository implements DriverWriteRepositoryInterface
{
    public function create(array $attributes): Driver
    {
        return Driver::create($attributes);
    }

    public function update(Driver $driver, array $attributes): Driver
    {
        $driver->update($attributes);

        return $driver->fresh() ?? $driver;
    }

    public function softDelete(Driver $driver): void
    {
        $driver->delete();
    }

    public function attachCompany(int $driverId, int $companyId, CarbonInterface $joinedAt): void
    {
        DB::table('driver_company')->insert([
            'driver_id' => $driverId,
            'company_id' => $companyId,
            'joined_at' => $joinedAt->toDateString(),
            'left_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function setLeaveDate(int $pivotId, CarbonInterface $leftAt): void
    {
        DB::table('driver_company')
            ->where('id', $pivotId)
            ->update([
                'left_at' => $leftAt->toDateString(),
                'updated_at' => now(),
            ]);
    }

    public function deleteMembership(int $pivotId): void
    {
        DB::table('driver_company')
            ->where('id', $pivotId)
            ->delete();
    }
}
