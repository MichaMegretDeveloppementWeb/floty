<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Models\Contract;

/**
 * Écritures Contract — interface slim conforme ADR-0013.
 *
 * Aucune décision métier ici (validation overlap, transactions
 * multi-entités, etc.) — c'est le rôle des Actions du domaine.
 */
interface ContractWriteRepositoryInterface
{
    public function create(StoreContractData $data): Contract;

    public function update(int $contractId, UpdateContractData $data): Contract;

    /**
     * Soft delete d'un contrat. Le trigger MySQL anti-overlap exclut
     * automatiquement les contrats `deleted_at IS NOT NULL`, donc une
     * suppression libère l'invariant pour la même plage.
     */
    public function delete(int $contractId): void;

    /**
     * Création atomique de plusieurs contrats — utilisée par
     * {@see BulkCreateContractsAction}. La
     * transaction est portée par l'Action ; ce repo se contente
     * d'enchaîner les inserts.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int> IDs des contrats créés, dans le même ordre.
     */
    public function insertManyRows(array $rows): array;
}
