<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Enums\Driver\FutureContractsResolutionMode;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\LeaveResolutionInvalidException;
use App\Models\Contract;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marks the end of a driver's membership in a company and resolves
 * their future contracts in that company.
 *
 * Pipeline:
 *   1. Locate the active membership (left_at NULL).
 *   2. List future contracts (start_date > leftAt) of the driver in
 *      this company.
 *   3. Apply the resolution mode:
 *      - None    : nothing to resolve, just set `left_at`.
 *      - Replace : validate the whole `replacementMap` first, then
 *        for each contract detach the leaving driver and attach the
 *        designated replacement (or null = detach only). Other drivers
 *        on the contract are preserved.
 *      - Detach  : remove the leaving driver from every future
 *        contract in one batch query.
 *   4. Set `left_at` on the pivot.
 *
 * Validation and writes are split in two passes to avoid a partial
 * rollback: the full `replacementMap` is validated before the
 * transaction opens.
 */
final class LeaveDriverCompanyMembershipAction
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
        private readonly DriverWriteRepositoryInterface $driverWriteRepo,
        private readonly ContractWriteRepositoryInterface $contractWriteRepo,
        private readonly CompanyReadRepositoryInterface $companyReadRepo,
    ) {}

    public function execute(Driver $driver, int $companyId, LeaveDriverCompanyMembershipData $data): void
    {
        $leftAt = Carbon::parse($data->leftAt);

        $pivot = $this->driverReadRepo->findActiveMembership($driver->id, $companyId);
        if ($pivot === null) {
            throw DriverMembershipNotFoundException::forActiveMembership($driver->id, $companyId);
        }

        $futureContracts = $this->driverReadRepo->listFutureContractsInCompany(
            $driver->id,
            $companyId,
            $leftAt,
        );

        if (
            $data->futureContractsResolution === FutureContractsResolutionMode::Replace
            && $futureContracts->isNotEmpty()
        ) {
            $this->validateReplacementMap($driver, $companyId, $futureContracts, $data->replacementMap);
        }

        $sortantDriverId = $driver->id;
        DB::transaction(function () use ($pivot, $leftAt, $futureContracts, $data, $sortantDriverId): void {
            if ($futureContracts->isNotEmpty()) {
                match ($data->futureContractsResolution) {
                    FutureContractsResolutionMode::Replace => $this->applyReplace($sortantDriverId, $futureContracts, $data->replacementMap),
                    FutureContractsResolutionMode::Detach => $this->applyDetach($sortantDriverId, $futureContracts),
                    FutureContractsResolutionMode::None => null,
                };
            }

            $this->driverWriteRepo->setLeaveDate((int) $pivot->id, $leftAt);
        });
    }

    /**
     * Pure validation: throws if the replacement map is inconsistent
     * (missing entry, invalid driver, replacement inactive on the
     * period, or self-replacement).
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  array<int, ?int>  $replacementMap
     */
    private function validateReplacementMap(
        Driver $driver,
        int $companyId,
        Collection $contracts,
        array $replacementMap,
    ): void {
        $company = $this->companyReadRepo->findById($companyId);
        if ($company === null) {
            // Degenerate case: the pivot existed but the company is gone.
            // Should never happen given the restrictOnDelete on the
            // pivot; defence in depth.
            throw DriverMembershipNotFoundException::forActiveMembership($driver->id, $companyId);
        }

        foreach ($contracts as $contract) {
            if (! array_key_exists($contract->id, $replacementMap)) {
                throw LeaveResolutionInvalidException::missingReplacement($contract->id);
            }

            $replacementId = $replacementMap[$contract->id];
            if ($replacementId === null) {
                continue;
            }

            if ($replacementId === $driver->id) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }

            // The replacement must be a new driver to add; reject when
            // they are already attached to the contract.
            $alreadyAttachedIds = $contract->drivers->pluck('id')->all();
            if (in_array($replacementId, $alreadyAttachedIds, true)) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }

            $replacement = $this->driverReadRepo->findById($replacementId);
            if ($replacement === null) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }

            $isActive = $replacement->isActiveInCompanyDuring(
                $company,
                Carbon::parse($contract->start_date),
                Carbon::parse($contract->end_date),
            );

            if (! $isActive) {
                throw LeaveResolutionInvalidException::replacementDriverInvalid($contract->id, $replacementId);
            }
        }
    }

    /**
     * Pure mutation pass; validation already done upstream. Detaches
     * the leaving driver from each pivot and attaches the designated
     * replacement when provided. Other drivers on each contract are
     * preserved.
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  array<int, ?int>  $replacementMap
     */
    private function applyReplace(int $sortantDriverId, Collection $contracts, array $replacementMap): void
    {
        foreach ($contracts as $contract) {
            $contractId = (int) $contract->id;
            $this->contractWriteRepo->bulkDetachDriver([$contractId], $sortantDriverId);

            $replacementId = $replacementMap[$contractId] ?? null;
            if ($replacementId !== null) {
                $this->contractWriteRepo->attachDriver($contractId, $replacementId);
            }
        }
    }

    /**
     * Removes the leaving driver from every future contract in one
     * batch pivot query; other drivers are preserved.
     *
     * @param  Collection<int, Contract>  $contracts
     */
    private function applyDetach(int $sortantDriverId, Collection $contracts): void
    {
        $ids = $contracts->pluck('id')->map(fn ($v): int => (int) $v)->all();
        $this->contractWriteRepo->bulkDetachDriver($ids, $sortantDriverId);
    }
}
