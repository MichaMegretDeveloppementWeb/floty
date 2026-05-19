<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Enums\Contract\ContractType;
use App\Models\Contract;

/**
 * Contract writes · slim interface per ADR-0013.
 *
 * No business decision here (overlap validation, multi-entity
 * transactions, etc.) · that is the role of the domain Actions.
 *
 * `contract_type` is passed as a separate parameter, derived by the
 * Action via {@see Contract::deriveTypeFromDates()}.
 */
interface ContractWriteRepositoryInterface
{
    public function create(StoreContractData $data, ContractType $contractType): Contract;

    public function update(int $contractId, UpdateContractData $data, ContractType $contractType): Contract;

    /**
     * Soft-delete a contract. The MySQL anti-overlap trigger
     * automatically excludes contracts with `deleted_at IS NOT NULL`,
     * so deletion frees the invariant for the same range.
     */
    public function delete(int $contractId): void;

    /**
     * Atomic creation of several contracts · used by
     * {@see BulkCreateContractsAction}. The transaction is carried by
     * the Action; this repository simply chains the inserts.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int> IDs of created contracts, in the same order.
     */
    public function insertManyRows(array $rows): array;

    /**
     * Syncs the driver list of a contract (N:N pivot
     * `contract_drivers`). The full list is replaced: passing `[]`
     * detaches all drivers.
     *
     * @param  list<int>  $driverIds
     */
    public function syncDrivers(int $contractId, array $driverIds): void;

    /**
     * Attaches an extra driver to a contract without touching the other
     * drivers already associated. Idempotent: if the driver is already
     * attached, no-op (no duplicate row thanks to the pivot unique
     * constraint).
     */
    public function attachDriver(int $contractId, int $driverId): void;

    /**
     * Detaches a specific driver from several contracts in a single
     * query, without touching the other drivers present on those
     * contracts. Used by the "driver leaves a company" workflow · the
     * contract keeps its other drivers.
     *
     * @param  list<int>  $contractIds
     */
    public function bulkDetachDriver(array $contractIds, int $driverId): void;
}
