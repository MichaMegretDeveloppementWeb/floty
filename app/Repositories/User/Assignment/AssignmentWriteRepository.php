<?php

declare(strict_types=1);

namespace App\Repositories\User\Assignment;

use App\Contracts\Repositories\User\Assignment\AssignmentWriteRepositoryInterface;
use App\Data\User\Assignment\BulkCreateResultData;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des écritures Assignment.
 */
final class AssignmentWriteRepository implements AssignmentWriteRepositoryInterface
{
    public function createBulk(int $vehicleId, int $companyId, array $dates): BulkCreateResultData
    {
        $now = now();
        $rows = array_map(
            static fn (string $date): array => [
                'vehicle_id' => $vehicleId,
                'company_id' => $companyId,
                'driver_id' => null,
                'date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $dates,
        );

        $inserted = DB::table('assignments')->insertOrIgnore($rows);
        $requested = count($dates);

        return new BulkCreateResultData(
            requested: $requested,
            inserted: $inserted,
            skipped: $requested - $inserted,
        );
    }
}
