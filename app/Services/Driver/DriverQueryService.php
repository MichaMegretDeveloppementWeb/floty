<?php

declare(strict_types=1);

namespace App\Services\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Driver\DriverCompanyMembershipData;
use App\Data\User\Driver\DriverData;
use App\Data\User\Driver\DriverIndexQueryData;
use App\Data\User\Driver\DriverListItemCompanyTagData;
use App\Data\User\Driver\DriverListItemData;
use App\Data\User\Driver\DriverOptionData;
use App\Data\User\Driver\FutureContractRowData;
use App\Data\User\Driver\PaginatedDriverListData;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Pivot\DriverCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Query service composing Driver DTOs from raw models (ADR-0013 R3).
 */
final class DriverQueryService
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
    ) {}

    /**
     * Server-side paginated Driver index (ADR-0020).
     */
    public function listPaginated(DriverIndexQueryData $query): PaginatedDriverListData
    {
        $paginator = $this->driverReadRepo->paginateForIndex($query);

        $items = array_map(
            fn (Driver $driver): DriverListItemData => $this->mapDriverToListItem($driver),
            $paginator->items(),
        );

        return new PaginatedDriverListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    private function mapDriverToListItem(Driver $driver): DriverListItemData
    {
        /** @var Collection<int, Company> $activeCompanies */
        $activeCompanies = $driver->companies;
        $tags = $activeCompanies
            ->take(2)
            ->map(fn ($company): DriverListItemCompanyTagData => new DriverListItemCompanyTagData(
                companyId: $company->id,
                shortCode: $company->short_code,
                legalName: $company->legal_name,
                color: $company->color,
            ))
            ->all();

        return new DriverListItemData(
            id: $driver->id,
            fullName: $driver->full_name,
            initials: $driver->initials,
            activeCompanies: $tags,
            totalActiveCompaniesCount: (int) $driver->active_companies_count,
            contractsCount: (int) ($driver->contracts_count ?? 0),
        );
    }

    public function detail(int $driverId): ?DriverData
    {
        $driver = $this->driverReadRepo->findByIdWithRelations($driverId);
        if ($driver === null) {
            return null;
        }

        $contractsByCompany = $this->driverReadRepo->countContractsForDriverGroupedByCompany($driverId);

        $memberships = $driver->companies->map(function ($company) use ($contractsByCompany): DriverCompanyMembershipData {
            /** @var DriverCompany $pivot */
            $pivot = $company->getAttribute('pivot');

            return new DriverCompanyMembershipData(
                pivotId: $pivot->id,
                companyId: $company->id,
                companyShortCode: $company->short_code,
                companyLegalName: $company->legal_name,
                companyColor: $company->color,
                joinedAt: $pivot->joined_at->toDateString(),
                leftAt: $pivot->left_at?->toDateString(),
                // Matches the write-side `findActiveMembership` semantic
                // (`left_at IS NULL`). A membership with any leftAt set
                // · past, present or future · is no longer active.
                isCurrentlyActive: $pivot->left_at === null,
                contractsCount: $contractsByCompany[$company->id] ?? 0,
            );
        });

        return new DriverData(
            id: $driver->id,
            firstName: $driver->first_name,
            lastName: $driver->last_name,
            fullName: $driver->full_name,
            initials: $driver->initials,
            memberships: $memberships->all(),
            contractsCount: (int) ($driver->contracts_count ?? 0),
        );
    }

    /**
     * Driver picker options for the Contract form, filtered by company
     * and the exact contract period.
     *
     * @return array<int, DriverOptionData>
     */
    public function optionsForContract(
        int $companyId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        return $this->driverReadRepo
            ->listActiveInCompanyDuring($companyId, $start, $end)
            ->map(fn (Driver $driver): DriverOptionData => new DriverOptionData(
                id: $driver->id,
                fullName: $driver->full_name,
                initials: $driver->initials,
            ))
            ->all();
    }

    /**
     * Future-contracts preview used by the leave-membership modal to
     * pre-resolve replacement candidates. The leaving driver is
     * excluded from the candidates (cannot replace themselves);
     * already-attached drivers are also excluded (already on contract).
     *
     * @return list<FutureContractRowData>
     */
    public function futureContractsForLeavePreview(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): array {
        $contracts = $this->driverReadRepo->listFutureContractsInCompany(
            $driverId,
            $companyId,
            $leftAt,
        );

        // 1 batch query rather than N `listActiveInCompanyDuring` calls.
        // The per-period filter then runs in memory with the same
        // condition as the SQL ·
        // `joined_at <= start AND (left_at IS NULL OR left_at >= end)`.
        $allDriversInCompany = $this->driverReadRepo->listAllInCompanyWithMemberships($companyId);

        $rows = [];
        foreach ($contracts as $contract) {
            $start = $contract->start_date;
            $end = $contract->end_date;

            // Drivers currently attached to the contract (N:N pivot).
            // The leaving driver is included on purpose · the modal
            // shows the full context before the user decides.
            $currentDriversList = $contract->drivers
                ->map(fn (Driver $d): DriverOptionData => new DriverOptionData(
                    id: $d->id,
                    fullName: $d->full_name,
                    initials: $d->initials,
                ))
                ->values()
                ->all();
            $alreadyAttachedIds = $contract->drivers->pluck('id')->all();

            // Replacement candidates · active over the contract's exact
            // period, minus every already-attached driver.
            $candidates = $allDriversInCompany
                ->filter(fn (Driver $d): bool => $this->isDriverActiveInCompanyDuring($d, $start, $end))
                ->reject(fn (Driver $d): bool => in_array($d->id, $alreadyAttachedIds, true))
                ->map(fn (Driver $d): DriverOptionData => new DriverOptionData(
                    id: $d->id,
                    fullName: $d->full_name,
                    initials: $d->initials,
                ))
                ->values()
                ->all();

            $rows[] = new FutureContractRowData(
                contractId: $contract->id,
                vehicleLicensePlate: $contract->vehicle->license_plate,
                startDate: $start->toDateString(),
                endDate: $end->toDateString(),
                durationDays: (int) $start->diffInDays($end) + 1,
                contractType: $contract->contract_type,
                currentDrivers: $currentDriversList,
                candidates: $candidates,
            );
        }

        return $rows;
    }

    /**
     * Flat driver options for the Contracts Index driver filter (all
     * companies, all periods).
     *
     * @return array<int, DriverOptionData>
     */
    public function listForOptions(): array
    {
        return $this->driverReadRepo
            ->listAllForOptions()
            ->map(fn (Driver $driver): DriverOptionData => new DriverOptionData(
                id: $driver->id,
                fullName: $driver->full_name,
                initials: $driver->initials,
            ))
            ->all();
    }

    /**
     * In-memory mirror of `listActiveInCompanyDuring` · a driver is
     * "active in the company during [start, end]" iff at least one of
     * their memberships in the company covers the whole period.
     *
     * Delegates to {@see DriverCompany::coversPeriod()} which
     * implements `joined_at <= start AND (left_at IS NULL OR left_at >= end)`.
     *
     * Caller must pre-load `companies` filtered on the relevant company
     * (cf. `listAllInCompanyWithMemberships`).
     */
    private function isDriverActiveInCompanyDuring(
        Driver $driver,
        CarbonInterface $start,
        CarbonInterface $end,
    ): bool {
        $startCarbon = Carbon::instance($start);
        $endCarbon = Carbon::instance($end);

        foreach ($driver->companies as $company) {
            /** @var DriverCompany|null $pivot */
            $pivot = $company->getRelationValue('pivot');
            if ($pivot !== null && $pivot->coversPeriod($startCarbon, $endCarbon)) {
                return true;
            }
        }

        return false;
    }
}
