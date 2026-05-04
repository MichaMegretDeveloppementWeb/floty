<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Enums\Contract\ContractType;
use App\Models\Contract;

/**
 * Écritures Contract - interface slim conforme ADR-0013.
 *
 * Aucune décision métier ici (validation overlap, transactions
 * multi-entités, etc.) - c'est le rôle des Actions du domaine.
 *
 * **Refonte 04.K** : `contract_type` est passé en paramètre séparé,
 * dérivé par l'Action via {@see Contract::deriveTypeFromDates()}.
 */
interface ContractWriteRepositoryInterface
{
    public function create(StoreContractData $data, ContractType $contractType): Contract;

    public function update(int $contractId, UpdateContractData $data, ContractType $contractType): Contract;

    /**
     * Soft delete d'un contrat. Le trigger MySQL anti-overlap exclut
     * automatiquement les contrats `deleted_at IS NOT NULL`, donc une
     * suppression libère l'invariant pour la même plage.
     */
    public function delete(int $contractId): void;

    /**
     * Création atomique de plusieurs contrats - utilisée par
     * {@see BulkCreateContractsAction}. La
     * transaction est portée par l'Action ; ce repo se contente
     * d'enchaîner les inserts.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int> IDs des contrats créés, dans le même ordre.
     */
    public function insertManyRows(array $rows): array;

    /**
     * Réassigne (ou détache si null) le conducteur d'un contrat.
     * Utilisé par le workflow Q6 (sortie d'un driver d'une entreprise)
     * pour réaffecter individuellement les contrats à venir.
     */
    public function reassignDriver(int $contractId, ?int $driverId): void;

    /**
     * Réassigne (ou détache si null) le conducteur de plusieurs contrats
     * en une seule requête. Utilisé par le mode `detach` du workflow Q6
     * pour traiter en batch tous les contrats à venir d'un coup.
     *
     * @param  list<int>  $contractIds
     */
    public function bulkReassignDriver(array $contractIds, ?int $driverId): void;
}
