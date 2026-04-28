<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Assignment;

use App\Actions\Assignment\BulkCreateAssignmentsAction;

/**
 * Écritures sur le domaine Assignment.
 *
 * Repo pur : aucune décision métier, aucune transformation de données.
 * La préparation des rows (driver_id par défaut, timestamps, etc.) est
 * faite par {@see BulkCreateAssignmentsAction}.
 */
interface AssignmentWriteRepositoryInterface
{
    /**
     * Insère N lignes pré-préparées dans `assignments`. Les doublons
     * (couple × date actif) sont silencieusement ignorés via
     * `INSERT IGNORE` (UNIQUE soft-delete par triggers MySQL).
     *
     * Retourne le nombre de lignes effectivement insérées.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertManyRows(array $rows): int;
}
