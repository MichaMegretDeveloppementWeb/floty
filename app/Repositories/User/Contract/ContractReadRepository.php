<?php

declare(strict_types=1);

namespace App\Repositories\User\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Models\Contract;
use App\Models\Driver;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Contract reads · slim per ADR-0013.
 *
 * Zero transformation, zero business decision. DTO composition lives
 * in {@see ContractQueryService}.
 */
final class ContractReadRepository implements ContractReadRepositoryInterface
{
    public function findById(int $id): ?Contract
    {
        return Contract::query()->find($id);
    }

    public function findByIdWithRelations(int $id): ?Contract
    {
        return Contract::query()
            ->with([
                'vehicle.fiscalCharacteristics',
                'company',
                'drivers',
            ])
            ->find($id);
    }

    public function findByIdsWithRelations(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Contract::query()
            ->whereIn('id', $ids)
            ->with([
                'vehicle.fiscalCharacteristics',
                'company',
                'drivers',
            ])
            ->get();
    }

    public function findByVehicleAndYear(int $vehicleId, int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        // Contracts crossing the year: start_date <= 31/12 AND end_date >= 01/01.
        return Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $yearEnd)
            ->where('end_date', '>=', $yearStart)
            ->orderBy('start_date')
            ->get();
    }

    public function findActiveForYear(int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return Contract::query()
            ->where('start_date', '<=', $yearEnd)
            ->where('end_date', '>=', $yearStart)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get();
    }

    public function findActiveForYearRange(int $from, int $to): Collection
    {
        $rangeStart = sprintf('%04d-01-01', $from);
        $rangeEnd = sprintf('%04d-12-31', $to);

        return Contract::query()
            ->where('start_date', '<=', $rangeEnd)
            ->where('end_date', '>=', $rangeStart)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get();
    }

    public function findForCompanyAndYear(int $companyId, int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return Contract::query()
            ->with(['vehicle'])
            ->where('company_id', $companyId)
            ->where('start_date', '<=', $yearEnd)
            ->where('end_date', '>=', $yearStart)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }

    public function listForCompany(int $companyId): Collection
    {
        return Contract::query()
            ->with([
                'vehicle:id,license_plate,exit_date,exit_reason',
                'company:id,short_code,legal_name,color',
                'drivers:id,first_name,last_name',
            ])
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function listForVehicle(int $vehicleId): Collection
    {
        return Contract::query()
            ->with([
                'vehicle:id,license_plate,exit_date,exit_reason',
                'company:id,short_code,legal_name,color',
                'drivers:id,first_name,last_name',
            ])
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function findForVehiclesInYear(array $vehicleIds, int $year): Collection
    {
        if ($vehicleIds === []) {
            return new Collection;
        }

        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);

        return Contract::query()
            ->with(['vehicle:id,exit_date'])
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, Driver>
     */
    public function driversForVehicleOnDate(int $vehicleId, CarbonImmutable $date): array
    {
        return Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->with('drivers:id,first_name,last_name,email')
            ->get()
            ->flatMap(static fn (Contract $contract): Collection => $contract->drivers)
            ->unique('id')
            ->values()
            ->all();
    }

    public function findWindowContractsForVehicle(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        return Contract::query()
            ->with([
                'vehicle:id,license_plate,exit_date',
                'company:id,short_code,legal_name,color',
            ])
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    public function findOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): ?Contract {
        $query = Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->first();
    }

    public function findAllOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
    ): Collection {
        return Contract::query()
            ->with('company:id,short_code,legal_name,color')
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('start_date')
            ->get();
    }

    public function findAllOverlappingForVehicles(
        array $vehicleIds,
        string $startDate,
        string $endDate,
    ): Collection {
        if ($vehicleIds === []) {
            return new Collection;
        }

        return Contract::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get();
    }

    public function existsAny(): bool
    {
        return Contract::query()->exists();
    }

    public function paginateForIndex(ContractIndexQueryData $query): LengthAwarePaginator
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Contract::query()
            ->select('contracts.*')
            ->with([
                'vehicle:id,license_plate,exit_date,exit_reason',
                // Eager load VFCs required by the fiscal pipeline run
                // during DTO enrichment (`fullYearTax`); avoids N+1 lazy
                // loads per contract.
                'vehicle.fiscalCharacteristics' => fn ($q) => $q->orderByDesc('effective_from'),
                'company:id,short_code,legal_name,color',
                'drivers:id,first_name,last_name',
            ]);

        if ($query->vehicleId !== null) {
            $eloquentQuery->where('contracts.vehicle_id', $query->vehicleId);
        }
        if ($query->companyId !== null) {
            $eloquentQuery->where('contracts.company_id', $query->companyId);
        }
        if ($query->driverId !== null) {
            $driverId = $query->driverId;
            $eloquentQuery->whereHas('drivers', fn (Builder $qd) => $qd
                ->where('drivers.id', $driverId));
        }
        if ($query->type !== null) {
            $eloquentQuery->where('contracts.contract_type', $query->type);
        }

        // Period filter: overlap with [periodStart, periodEnd].
        // `effectivePeriod()` derives the full year when `year` is
        // present (UI "Year" toggle), otherwise returns the raw bounds.
        $period = $query->effectivePeriod();
        if ($period['periodStart'] !== null) {
            $eloquentQuery->where('contracts.end_date', '>=', $period['periodStart']);
        }
        if ($period['periodEnd'] !== null) {
            $eloquentQuery->where('contracts.start_date', '<=', $period['periodEnd']);
        }

        // Combo search: LIKE on vehicle/company/driver via whereHas.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function (Builder $w) use ($term): void {
                $w->whereHas('vehicle', fn (Builder $qv) => $qv
                    ->where('license_plate', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term))
                    ->orWhereHas('company', fn (Builder $qc) => $qc
                        ->where('short_code', 'like', $term)
                        ->orWhere('legal_name', 'like', $term))
                    ->orWhereHas('drivers', fn (Builder $qd) => $qd
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term));
            });
        }

        // `vehicle` and `company` sort use a temporary leftJoin to
        // order by the relation's textual column.
        match ($query->sortKey) {
            'vehicle' => $eloquentQuery
                ->leftJoin('vehicles', 'contracts.vehicle_id', '=', 'vehicles.id')
                ->orderBy('vehicles.license_plate', $direction),
            'company' => $eloquentQuery
                ->leftJoin('companies', 'contracts.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'startDate' => $eloquentQuery->orderBy('contracts.start_date', $direction),
            'endDate' => $eloquentQuery->orderBy('contracts.end_date', $direction),
            'duration' => $eloquentQuery->orderByRaw(
                'DATEDIFF(contracts.end_date, contracts.start_date) '.($direction === 'desc' ? 'desc' : 'asc'),
            ),
            'type' => $eloquentQuery->orderBy('contracts.contract_type', $direction),
            default => $eloquentQuery->orderByDesc('contracts.start_date'),
        };

        return $eloquentQuery->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function findAllInWindow(string $start, string $end): Collection
    {
        return Contract::query()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get(['id', 'vehicle_id', 'start_date', 'end_date']);
    }

    public function countForDriverInCompany(int $driverId, int $companyId): int
    {
        return Contract::query()
            ->whereHas('drivers', fn (Builder $q) => $q->where('drivers.id', $driverId))
            ->where('company_id', $companyId)
            ->count();
    }

    public function countForCompany(int $companyId): int
    {
        return Contract::query()
            ->where('company_id', $companyId)
            ->count();
    }

    public function statsForCompanyInPeriod(
        int $companyId,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        $query = Contract::query()->where('company_id', $companyId);

        if ($periodStart !== null) {
            $query->where('end_date', '>=', $periodStart);
        }

        if ($periodEnd !== null) {
            $query->where('start_date', '<=', $periodEnd);
        }

        // Clamp start/end to the filtered window for day cumulation.
        // Without window, raw contract duration. Parameterised bindings
        // for defence in depth (the DTO already validates `date_format`).
        $clampedEndExpr = $periodEnd !== null ? 'LEAST(end_date, ?)' : 'end_date';
        $clampedStartExpr = $periodStart !== null ? 'GREATEST(start_date, ?)' : 'start_date';

        $totalDaysExpr = "COALESCE(SUM(DATEDIFF({$clampedEndExpr}, {$clampedStartExpr}) + 1), 0) AS total_days";
        $bindings = [];
        if ($periodEnd !== null) {
            $bindings[] = $periodEnd;
        }
        if ($periodStart !== null) {
            $bindings[] = $periodStart;
        }

        // `toBase()` switches to QueryBuilder: `first()` returns
        // ?stdClass (not ?Contract), letting selectRaw-aliased columns
        // be read without a Model declaration.
        $row = (clone $query)
            ->toBase()
            ->selectRaw($totalDaysExpr, $bindings)
            ->selectRaw("SUM(CASE WHEN contract_type = 'lcd' THEN 1 ELSE 0 END) AS lcd_count")
            ->selectRaw("SUM(CASE WHEN contract_type = 'lld' THEN 1 ELSE 0 END) AS lld_count")
            ->first();

        if ($row === null) {
            return ['totalDays' => 0, 'lcdCount' => 0, 'lldCount' => 0];
        }

        return [
            'totalDays' => (int) ($row->total_days ?? 0),
            'lcdCount' => (int) ($row->lcd_count ?? 0),
            'lldCount' => (int) ($row->lld_count ?? 0),
        ];
    }

    public function firstContractYearForCompany(int $companyId): ?int
    {
        $row = DB::table('contracts')
            ->where('company_id', $companyId)
            ->selectRaw('YEAR(MIN(start_date)) AS first_year')
            ->first();

        if ($row === null || $row->first_year === null) {
            return null;
        }

        return (int) $row->first_year;
    }

    public function findForCompanyInPeriod(
        int $companyId,
        string $startDate,
        string $endDate,
    ): Collection {
        // `exit_date` is included in the vehicle selection so the
        // `BillingCalculator` can clip billable days to the exit date
        // without N+1.
        return Contract::query()
            ->with('vehicle:id,license_plate,brand,model,exit_date')
            ->where('company_id', $companyId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get();
    }

    public function findForCompaniesInPeriod(
        array $companyIds,
        string $startDate,
        string $endDate,
    ): Collection {
        if ($companyIds === []) {
            return new Collection;
        }

        // Same projection as findForCompanyInPeriod (exit_date included
        // for day clipping), batched over several companies in one query.
        return Contract::query()
            ->with('vehicle:id,license_plate,brand,model,exit_date')
            ->whereIn('company_id', $companyIds)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('company_id')
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get();
    }

    public function yearBounds(): array
    {
        $row = DB::table('contracts')
            ->whereNull('deleted_at')
            ->selectRaw('YEAR(MIN(start_date)) AS min_year, YEAR(MAX(start_date)) AS max_year')
            ->first();

        return [
            'min' => $row?->min_year !== null ? (int) $row->min_year : null,
            'max' => $row?->max_year !== null ? (int) $row->max_year : null,
        ];
    }

    public function findContractDateRangesForVehicle(int $vehicleId): Collection
    {
        return Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->get(['id', 'company_id', 'start_date', 'end_date']);
    }

    public function countContractsByDriverForCompany(int $companyId): array
    {
        // Aggregation through the N:N pivot `contract_drivers` to count
        // contracts per driver for a given company. The SoftDeletes
        // scope on Contract is applied automatically by Eloquent · rows
        // with `deleted_at IS NOT NULL` are excluded without an explicit
        // `whereNull`.
        return Contract::query()
            ->join('contract_drivers', 'contracts.id', '=', 'contract_drivers.contract_id')
            ->where('contracts.company_id', $companyId)
            ->selectRaw('contract_drivers.driver_id, COUNT(*) as cnt')
            ->groupBy('contract_drivers.driver_id')
            ->pluck('cnt', 'driver_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();
    }

    public function findActiveYearsForCompany(int $companyId): array
    {
        $rows = Contract::query()
            ->where('company_id', $companyId)
            ->get(['start_date', 'end_date']);

        $years = [];
        foreach ($rows as $contract) {
            $startYear = (int) $contract->start_date->format('Y');
            $endYear = (int) $contract->end_date->format('Y');
            for ($y = $startYear; $y <= $endYear; $y++) {
                $years[$y] = true;
            }
        }

        $list = array_keys($years);
        sort($list);

        return $list;
    }
}
