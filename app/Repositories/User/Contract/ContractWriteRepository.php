<?php

declare(strict_types=1);

namespace App\Repositories\User\Contract;

use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Models\Contract;

/**
 * Implémentation Eloquent des écritures Contract — slim conforme
 * ADR-0013 (zéro transformation, zéro décision métier ; les Actions
 * portent les transactions multi-entités et les pré-validations
 * applicatives).
 */
final class ContractWriteRepository implements ContractWriteRepositoryInterface
{
    public function create(StoreContractData $data): Contract
    {
        return Contract::create([
            'vehicle_id' => $data->vehicleId,
            'company_id' => $data->companyId,
            'driver_id' => $data->driverId,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'contract_reference' => $data->contractReference,
            'contract_type' => $data->contractType,
            'notes' => $data->notes,
        ]);
    }

    public function update(int $contractId, UpdateContractData $data): Contract
    {
        $contract = Contract::findOrFail($contractId);

        $contract->update([
            'vehicle_id' => $data->vehicleId,
            'company_id' => $data->companyId,
            'driver_id' => $data->driverId,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'contract_reference' => $data->contractReference,
            'contract_type' => $data->contractType,
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
}
