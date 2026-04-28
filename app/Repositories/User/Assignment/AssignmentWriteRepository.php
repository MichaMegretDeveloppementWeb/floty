<?php

declare(strict_types=1);

namespace App\Repositories\User\Assignment;

use App\Contracts\Repositories\User\Assignment\AssignmentWriteRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des écritures Assignment — pure persistence.
 */
final class AssignmentWriteRepository implements AssignmentWriteRepositoryInterface
{
    public function insertManyRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return DB::table('assignments')->insertOrIgnore($rows);
    }
}
