<?php

declare(strict_types=1);

namespace App\Repositories\User\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\Driver\DriverIndexQueryData;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Pivot\DriverCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent implementation of Driver reads · slim per ADR-0013.
 */
final class DriverReadRepository implements DriverReadRepositoryInterface
{
    public function findById(int $id): ?Driver
    {
        return Driver::query()->find($id);
    }

    public function findByIdWithRelations(int $id): ?Driver
    {
        return Driver::query()
            ->with(['companies' => function ($query): void {
                $query->orderByPivot('joined_at');
            }])
            ->withCount('contracts')
            ->find($id);
    }

    public function existsAny(): bool
    {
        return Driver::query()->exists();
    }

    public function paginateForIndex(DriverIndexQueryData $query): LengthAwarePaginator
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Driver::query()
            ->with(['companies' => function ($q): void {
                $q->whereNull('driver_company.left_at')
                    ->orderByPivot('joined_at');
            }])
            ->withCount('contracts')
            ->withCount(['companies as active_companies_count' => function ($q): void {
                $q->whereNull('driver_company.left_at');
            }]);

        // Name search (LIKE on first_name OR last_name).
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function ($w) use ($term): void {
                $w->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }

        // Company filter · drivers actively bound to the company
        // (open membership, left_at IS NULL). Closed memberships are
        // excluded: a driver who left the company no longer appears
        // when filtering on it.
        if ($query->companyId !== null) {
            $companyId = $query->companyId;
            $eloquentQuery->whereHas('companies', function ($q) use ($companyId): void {
                $q->where('companies.id', $companyId)
                    ->whereNull('driver_company.left_at');
            });
        }

        // Activity status filter · at least one open membership / none.
        if ($query->activityStatus === 'active') {
            $eloquentQuery->whereHas('companies', function ($q): void {
                $q->whereNull('driver_company.left_at');
            });
        } elseif ($query->activityStatus === 'inactive') {
            $eloquentQuery->whereDoesntHave('companies', function ($q): void {
                $q->whereNull('driver_company.left_at');
            });
        }

        // Contracts scope filter · with / without contracts.
        if ($query->contractsScope === 'with') {
            $eloquentQuery->has('contracts');
        } elseif ($query->contractsScope === 'without') {
            $eloquentQuery->doesntHave('contracts');
        }

        // Whitelisted sort (cf. DriverIndexQueryData::allowedSortKeys()).
        match ($query->sortKey) {
            'fullName' => $eloquentQuery
                ->orderBy('last_name', $direction)
                ->orderBy('first_name', $direction),
            'contractsCount' => $eloquentQuery->orderBy('contracts_count', $direction),
            'activeCompaniesCount' => $eloquentQuery->orderBy('active_companies_count', $direction),
            // No explicit sortKey: default alphabetical sort.
            default => $eloquentQuery->orderBy('last_name')->orderBy('first_name'),
        };

        return $eloquentQuery->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function listAllForOptions(): Collection
    {
        return Driver::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function listForCompany(int $companyId, bool $includeInactive = true): Collection
    {
        $query = Driver::query()
            ->whereHas('companies', function ($q) use ($companyId, $includeInactive): void {
                $q->where('companies.id', $companyId);
                if (! $includeInactive) {
                    $q->whereNull('driver_company.left_at');
                }
            })
            ->with(['companies' => function ($q) use ($companyId): void {
                $q->where('companies.id', $companyId);
            }])
            ->orderBy('last_name')
            ->orderBy('first_name');

        return $query->get();
    }

    public function listActiveInCompanyDuring(
        int $companyId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return Driver::query()
            ->whereHas('companies', function ($q) use ($companyId, $startDate, $endDate): void {
                $q->where('companies.id', $companyId)
                    ->where('driver_company.joined_at', '<=', $startDate)
                    ->where(function ($inner) use ($endDate): void {
                        $inner->whereNull('driver_company.left_at')
                            ->orWhere('driver_company.left_at', '>=', $endDate);
                    });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function listAllInCompanyWithMemberships(int $companyId): Collection
    {
        return Driver::query()
            ->whereHas('companies', fn (Builder $q) => $q->where('companies.id', $companyId))
            ->with(['companies' => fn ($q) => $q->where('companies.id', $companyId)])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function countFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): int {
        return Contract::query()
            ->whereHas('drivers', fn (Builder $q) => $q->where('drivers.id', $driverId))
            ->where('company_id', $companyId)
            ->where('start_date', '>', $leftAt->toDateString())
            ->count();
    }

    public function listFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): Collection {
        return Contract::query()
            ->with([
                'vehicle:id,license_plate',
                'company:id,short_code,legal_name,color',
                'drivers:id,first_name,last_name',
            ])
            ->whereHas('drivers', fn (Builder $q) => $q->where('drivers.id', $driverId))
            ->where('company_id', $companyId)
            ->where('start_date', '>', $leftAt->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    public function countContractsForDriverGroupedByCompany(int $driverId): array
    {
        return Contract::query()
            ->whereHas('drivers', fn (Builder $q) => $q->where('drivers.id', $driverId))
            ->selectRaw('company_id, COUNT(*) as aggregate')
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    public function findActiveMembership(int $driverId, int $companyId): ?DriverCompany
    {
        return DriverCompany::query()
            ->where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->whereNull('left_at')
            ->orderByDesc('joined_at')
            ->first();
    }

    public function findMembershipById(int $pivotId): ?DriverCompany
    {
        return DriverCompany::query()->find($pivotId);
    }

    public function countContractsForDriver(int $driverId): int
    {
        return Contract::query()
            ->whereHas('drivers', fn (Builder $q) => $q->where('drivers.id', $driverId))
            ->count();
    }
}
