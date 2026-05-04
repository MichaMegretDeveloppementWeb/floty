<?php

declare(strict_types=1);

namespace App\Repositories\User\Contract;

use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Enums\Contract\ContractType;
use App\Models\Contract;

/**
 * Implémentation Eloquent des écritures Contract - slim conforme
 * ADR-0013 (zéro transformation, zéro décision métier ; les Actions
 * portent les transactions multi-entités et les pré-validations
 * applicatives).
 *
 * **Refonte 04.K** : `contract_type` est désormais dérivé par l'Action
 * (cf. {@see Contract::deriveTypeFromDates()}) et passé au writer en
 * paramètre séparé - le DTO ne le porte plus.
 */
final class ContractWriteRepository implements ContractWriteRepositoryInterface
{
    public function create(StoreContractData $data, ContractType $contractType): Contract
    {
        return Contract::create([
            'vehicle_id' => $data->vehicleId,
            'company_id' => $data->companyId,
            'driver_id' => $data->driverId,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'contract_reference' => $data->contractReference,
            'contract_type' => $contractType,
            'notes' => $data->notes,
        ]);
    }

    public function update(int $contractId, UpdateContractData $data, ContractType $contractType): Contract
    {
        $contract = Contract::findOrFail($contractId);

        $contract->update([
            'vehicle_id' => $data->vehicleId,
            'company_id' => $data->companyId,
            'driver_id' => $data->driverId,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'contract_reference' => $data->contractReference,
            'contract_type' => $contractType,
            'notes' => $data->notes,
        ]);

        return $contract->fresh();
    }

    public function delete(int $contractId): void
    {
        Contract::query()->where('id', $contractId)->delete();
    }

    public function insertManyRows(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $contract = Contract::create($row);
            $ids[] = $contract->id;
        }

        return $ids;
    }

    public function reassignDriver(int $contractId, ?int $driverId): void
    {
        Contract::query()
            ->where('id', $contractId)
            ->update(['driver_id' => $driverId]);
    }

    public function bulkReassignDriver(array $contractIds, ?int $driverId): void
    {
        if ($contractIds === []) {
            return;
        }

        Contract::query()
            ->whereIn('id', $contractIds)
            ->update(['driver_id' => $driverId]);
    }
}
