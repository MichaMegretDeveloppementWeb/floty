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
use App\Data\User\Driver\PaginatedDriverListData;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Pivot\DriverCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Composition des DTOs Driver à partir des Models bruts (ADR-0013 R3).
 */
final class DriverQueryService
{
    public function __construct(
        private readonly DriverReadRepositoryInterface $driverReadRepo,
    ) {}

    /**
     * Index drivers paginé server-side (cf. ADR-0020). Délègue au repo
     * pour la query SQL puis mappe les models en DTO de présentation.
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

        $today = Carbon::today();
        $contractsByCompany = $this->driverReadRepo->countContractsForDriverGroupedByCompany($driverId);

        $memberships = $driver->companies->map(function ($company) use ($contractsByCompany, $today): DriverCompanyMembershipData {
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
                isCurrentlyActive: $pivot->left_at === null || $pivot->left_at->greaterThanOrEqualTo($today),
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
     * Options pour le picker driver dans le formulaire Contract - filtré
     * par company + période exacte (Q4).
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
     * Options plates pour le filtre conducteur du Contracts Index (toutes
     * companies, toutes périodes).
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
}
