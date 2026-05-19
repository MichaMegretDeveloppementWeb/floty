<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Atomically creates N contracts sharing a common range, type and
 * tenant company; typically the quick multi-vehicle assignment from
 * the planning view (ADR-0013 R3).
 *
 * Transactional behavior: the transaction starts before the first
 * overlap check. If any vehicle conflicts, the exception aborts the
 * batch and the transaction is rolled back (no contract created).
 *
 * @return list<int> created contract IDs, in the same order as `vehicleIds`
 */
final readonly class BulkCreateContractsAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    /**
     * @return list<int>
     */
    public function execute(BulkStoreContractsData $data): array
    {
        // Range shared by all vehicleIds by DTO construction, so the
        // contract type is computed once for the entire batch.
        $contractType = Contract::deriveTypeFromDates($data->startDate, $data->endDate);

        return DB::transaction(function () use ($data, $contractType): array {
            // One batch query instead of N findOverlapping calls.
            // Fails fast in memory on the first conflict to keep the
            // historic semantics (whole batch rejected).
            $existingOverlaps = $this->reader->findAllOverlappingForVehicles(
                vehicleIds: $data->vehicleIds,
                startDate: $data->startDate,
                endDate: $data->endDate,
            );

            // Index by vehicle_id for O(1) lookup. Each entry holds the
            // first conflicting contract (rows are sorted by
            // `vehicle_id, start_date` SQL-side, so the first occurrence
            // per vehicle is deterministic).
            $overlapByVehicleId = [];
            foreach ($existingOverlaps as $contract) {
                $overlapByVehicleId[$contract->vehicle_id] ??= $contract;
            }

            $rows = [];
            foreach ($data->vehicleIds as $vehicleId) {
                $conflict = $overlapByVehicleId[$vehicleId] ?? null;

                if ($conflict !== null) {
                    throw ContractOverlapException::fromConflict(
                        vehicleId: $vehicleId,
                        startDate: $data->startDate,
                        endDate: $data->endDate,
                        conflictingContractId: $conflict->id,
                        conflictingStartDate: $conflict->start_date->toDateString(),
                        conflictingEndDate: $conflict->end_date->toDateString(),
                    );
                }

                $rows[] = [
                    'vehicle_id' => $vehicleId,
                    'company_id' => $data->companyId,
                    'start_date' => $data->startDate,
                    'end_date' => $data->endDate,
                    'contract_reference' => $data->contractReference,
                    'contract_type' => $contractType->value,
                    'notes' => $data->notes,
                ];
            }

            $ids = $this->writer->insertManyRows($rows);

            // Attach the same driver list to each created contract.
            foreach ($ids as $contractId) {
                $this->writer->syncDrivers($contractId, $data->driverIds);
            }

            return $ids;
        });
    }
}
