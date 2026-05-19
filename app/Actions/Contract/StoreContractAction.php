<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Data\User\Contract\StoreContractData;
use App\Exceptions\Contract\ContractOverlapException;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * Creates a contract with applicative anti-overlap validation, defence
 * in depth before the DB trigger (ADR-0014 D5).
 *
 * The MySQL `contracts_no_overlap_*` trigger remains the source of
 * truth: it guarantees the invariant against inter-request races that
 * the default READ COMMITTED transaction does not cover. The applicative
 * pre-check produces an explicit FR message when the conflict is
 * detectable upstream, and the transaction prevents Eloquent side
 * effects from leaving partial state on later failure.
 */
final readonly class StoreContractAction
{
    public function __construct(
        private ContractReadRepositoryInterface $reader,
        private ContractWriteRepositoryInterface $writer,
    ) {}

    public function execute(StoreContractData $data): Contract
    {
        return DB::transaction(function () use ($data): Contract {
            $conflict = $this->reader->findOverlapping(
                vehicleId: $data->vehicleId,
                startDate: $data->startDate,
                endDate: $data->endDate,
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

            $contractType = Contract::deriveTypeFromDates($data->startDate, $data->endDate);

            $contract = $this->writer->create($data, $contractType);

            $this->writer->syncDrivers($contract->id, $data->driverIds);

            return $contract->refresh();
        });
    }
}
