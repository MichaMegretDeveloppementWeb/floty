<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\UpdateContractData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Updates a contract with anti-overlap re-validation, defence in depth
 * (ADR-0014 D5). The current row is excluded from the conflict lookup
 * via `excludeId`. The MySQL `contracts_no_overlap_*` trigger remains
 * the source of truth against inter-request races; see
 * {@see StoreContractAction}.
 */
final readonly class UpdateContractAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $contractId, UpdateContractData $data): Contract
    {
        return DB::transaction(function () use ($contractId, $data): Contract {
            $conflict = $this->reader->findOverlapping(
                vehicleId: $data->vehicleId,
                startDate: $data->startDate,
                endDate: $data->endDate,
                excludeId: $contractId,
            );

            if ($conflict !== null) {
                throw ContractOverlapException::fromConflict(
                    vehicleId: $data->vehicleId,
                    startDate: $data->startDate,
                    endDate: $data->endDate,
                    conflictingContractId: $conflict->id,
                    conflictingStartDate: $conflict->start_date->toDateString(),
                    conflictingEndDate: $conflict->end_date->toDateString(),
                );
            }

            // Unconditional recompute: idempotent, cheap, no diff to track.
            $contractType = Contract::deriveTypeFromDates($data->startDate, $data->endDate);

            $contract = $this->writer->update($contractId, $data, $contractType);

            // `sync()` replaces the whole list (add/remove/keep against
            // the current delta).
            $this->writer->syncDrivers($contractId, $data->driverIds);

            return $contract->refresh();
        });
    }
}
