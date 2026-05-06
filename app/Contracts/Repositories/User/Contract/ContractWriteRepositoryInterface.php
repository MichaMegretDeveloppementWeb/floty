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
     * Synchronise la liste des conducteurs d'un contrat (pivot N:N
     * `contract_drivers`). Toute la liste est remplacée : passer `[]`
     * détache tous les conducteurs.
     *
     * @param  list<int>  $driverIds
     */
    public function syncDrivers(int $contractId, array $driverIds): void;

    /**
     * Attache un conducteur supplémentaire à un contrat sans toucher
     * aux autres conducteurs déjà associés. Idempotent : si le driver
     * est déjà attaché, no-op (pas de duplicate row grâce à la contrainte
     * unique du pivot).
     */
    public function attachDriver(int $contractId, int $driverId): void;

    /**
     * Détache un conducteur précis de plusieurs contrats en une seule
     * requête, sans toucher aux autres conducteurs présents sur ces
     * contrats. Utilisé par le workflow Q6 (sortie d'un driver d'une
     * entreprise) - le contrat conserve ses autres conducteurs.
     *
     * @param  list<int>  $contractIds
     */
    public function bulkDetachDriver(array $contractIds, int $driverId): void;
}
