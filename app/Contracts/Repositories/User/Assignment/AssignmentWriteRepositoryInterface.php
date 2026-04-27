<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Assignment;

use App\Data\User\Assignment\BulkCreateResultData;

/**
 * Écritures sur le domaine Assignment.
 */
interface AssignmentWriteRepositoryInterface
{
    /**
     * Création en masse d'attributions pour un couple sur N dates. Les
     * doublons (couple × date) sont silencieusement ignorés via
     * `INSERT IGNORE` (UNIQUE soft-delete par triggers).
     *
     * @param  list<string>  $dates  Format ISO Y-m-d
     */
    public function createBulk(int $vehicleId, int $companyId, array $dates): BulkCreateResultData;
}
