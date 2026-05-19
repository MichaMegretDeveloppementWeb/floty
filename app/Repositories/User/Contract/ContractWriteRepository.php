<?php

declare(strict_types=1);

namespace App\Repositories\User\Contract;

use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Contract writes · slim per ADR-0013.
 *
 * Zero transformation, zero business decision; Actions carry the
 * multi-entity transactions and the application-level pre-validations.
 *
 * `contract_type` is derived by the Action (cf.
 * {@see Contract::deriveTypeFromDates()}) and passed to the writer as
 * a separate parameter.
 */
final class ContractWriteRepository implements ContractWriteRepositoryInterface
{
    public function create(StoreContractData $data, ContractType $contractType): Contract
    {
        return Contract::create([
            'vehicle_id' => $data->vehicleId,
            'company_id' => $data->companyId,
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

    public function syncDrivers(int $contractId, array $driverIds): void
    {
        $contract = Contract::findOrFail($contractId);
        $contract->drivers()->sync($driverIds);
    }

    public function attachDriver(int $contractId, int $driverId): void
    {
        $contract = Contract::findOrFail($contractId);
        // syncWithoutDetaching is idempotent: no duplicate if already attached.
        $contract->drivers()->syncWithoutDetaching([$driverId]);
    }

    public function bulkDetachDriver(array $contractIds, int $driverId): void
    {
        if ($contractIds === []) {
            return;
        }

        DB::table('contract_drivers')
            ->whereIn('contract_id', $contractIds)
            ->where('driver_id', $driverId)
            ->delete();
    }
}
