<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Data\User\Contract\ContractIndexQueryData;
use App\Models\Contract;
use App\Models\Driver;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contract reads · slim interface per ADR-0013.
 *
 * Zero transformation, zero business decision · returns raw Collections
 * and Models. DTO composition and aggregations live in
 * {@see ContractQueryService}.
 */
interface ContractReadRepositoryInterface
{
    public function findById(int $id): ?Contract;

    public function findByIdWithRelations(int $id): ?Contract;

    /**
     * Drivers (with their email) of the contract(s) active on `$date` for a
     * vehicle, deduped by id. Used to inject the conductor into control
     * reminders (Chantier B / B3).
     *
     * @return array<int, Driver>
     */
    public function driversForVehicleOnDate(int $vehicleId, CarbonImmutable $date): array;

    /**
     * Batch variant of {@see findByIdWithRelations} · loads in one query
     * the contracts (with their vehicle, vehicle.fiscalCharacteristics,
     * company, drivers relations) for a list of IDs.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Contract>
     */
    public function findByIdsWithRelations(array $ids): Collection;

    /**
     * Active contracts for a vehicle over a given year · used by the
     * fiscal engine for day expansion (R-2024-002).
     *
     * @return Collection<int, Contract>
     */
    public function findByVehicleAndYear(int $vehicleId, int $year): Collection;

    /**
     * All contracts active during the given calendar year · pivot of
     * the fiscal engine (composed into `ContractsByPair` in the service).
     * Minimal eager-load for aggregations.
     *
     * @return Collection<int, Contract>
     */
    public function findActiveForYear(int $year): Collection;

    /**
     * Range variant of {@see findActiveForYear} · all contracts active
     * during at least one year of `[from..to]` in a single SQL query.
     * Pivot consumed by
     * {@see ContractQueryService::loadContractsByPairForYearRange()} to
     * avoid N loads year-by-year on multi-year pages.
     *
     * @return Collection<int, Contract>
     */
    public function findActiveForYearRange(int $from, int $to): Collection;

    /**
     * Active contracts of a user company.
     *
     * @return Collection<int, Contract>
     */
    public function listForCompany(int $companyId): Collection;

    /**
     * All active contracts of `(company, year)` crossing the calendar
     * year (`start_date <= 31/12 AND end_date >= 01/01`). Eager-loads
     * `vehicle` for services consuming the vehicle attributes.
     *
     * Deterministic sort `start_date ASC, id ASC` required by chaining
     * algorithms: two contracts with equal start dates are ordered by
     * id to avoid any ambiguity.
     *
     * @return Collection<int, Contract>
     */
    public function findForCompanyAndYear(int $companyId, int $year): Collection;

    /**
     * All active contracts of a vehicle, all periods.
     *
     * @return Collection<int, Contract>
     */
    public function listForVehicle(int $vehicleId): Collection;

    /**
     * Batched variant for Index/listing usage · fetches in one SQL all
     * active contracts of the given vehicles overlapping the requested
     * calendar year. Avoids N+1 on paginated pages computing a yearly
     * rental price.
     *
     * Deterministic sort `start_date ASC, id ASC`. Minimal eager-load of
     * `vehicle` + `company`.
     *
     * @param  list<int>  $vehicleIds
     * @return Collection<int, Contract>
     */
    public function findForVehiclesInYear(array $vehicleIds, int $year): Collection;

    /**
     * Active contracts of a vehicle overlapping the `[start, end]`
     * window. Eager-loads `company` for the week drawer.
     *
     * @return Collection<int, Contract>
     */
    public function findWindowContractsForVehicle(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection;

    /**
     * Looks up an active contract on the same vehicle whose
     * `[start_date, end_date]` range overlaps the passed one. Used by
     * Store/Update Actions for application-level validation (defence in
     * depth before the DB trigger).
     *
     * `excludeId` skips the contract currently being edited.
     */
    public function findOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): ?Contract;

    /**
     * All active contracts of a vehicle overlapping `[start, end]`.
     * Unlike {@see findOverlapping} which returns the first conflict for
     * a boolean check, this method enumerates every conflict (typically
     * to expose the full list of conflicting dates to the user).
     *
     * @return Collection<int, Contract>
     */
    public function findAllOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
    ): Collection;

    /**
     * Batch variant of {@see findOverlapping} for multi-vehicle contract
     * creation on a shared range.
     *
     * Loads in one SQL all active contracts of the listed vehicles
     * overlapping the `[startDate, endDate]` range. The consumer
     * iterates in memory to decide which vehicle has a conflict · no
     * N×SELECT.
     *
     * @param  list<int>  $vehicleIds
     * @return Collection<int, Contract>
     */
    public function findAllOverlappingForVehicles(
        array $vehicleIds,
        string $startDate,
        string $endDate,
    ): Collection;

    /**
     * Server-side paginated list for the Contracts Index (ADR-0020).
     * Applies `{search, vehicleId, companyId, driverId, type,
     * periodStart, periodEnd, sortKey, sortDirection, page, perPage}`
     * from the DTO as raw SQL.
     *
     * Search: LIKE on `vehicle.license_plate, vehicle.brand,
     * vehicle.model, company.short_code, company.legal_name,
     * driver.first_name, driver.last_name` via `whereHas`.
     *
     * Sort whitelist: vehicle | company | startDate | endDate |
     * duration | type. `vehicle`/`company` use a temporary join to
     * order by the relation's textual column. `duration` uses
     * `DATEDIFF(end_date, start_date)`.
     *
     * Period filter: overlap (`start_date <= periodEnd AND end_date >=
     * periodStart`).
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginateForIndex(ContractIndexQueryData $query): LengthAwarePaginator;

    /**
     * Returns true iff at least one contract exists (`SELECT EXISTS`).
     * Used by the Index to distinguish an intrinsically empty table
     * from an active filter returning zero rows.
     */
    public function existsAny(): bool;

    /**
     * All active contracts (all license plates) overlapping the
     * `[start, end]` window. Used to pre-compute the
     * `vehicleId → busyDates` map consumed by the date picker on the
     * Contract Create/Edit form.
     *
     * @return Collection<int, Contract>
     */
    public function findAllInWindow(string $start, string $end): Collection;

    /**
     * Counts contracts referencing this driver in this company, all
     * periods. Used by `DetachDriverCompanyMembershipAction` to block
     * deleting a membership that still has contracts.
     */
    public function countForDriverInCompany(int $driverId, int $companyId): int;

    /**
     * Total count of contracts (non soft-deleted) of a company, all
     * fiscal years. Feeds the lifetime `contractsCount` stat on the
     * company detail page.
     */
    public function countForCompany(int $companyId): int;

    /**
     * Stats on contracts of a company within an optional time window.
     * `totalDays` is intersection-clamped to the window: a contract
     * 01/01–31/12 with a Q3 filter only counts July–September days.
     *
     * Without a window (`$periodStart` and `$periodEnd` null), returns
     * the lifetime totals across all contracts of the company.
     *
     * @return array{totalDays: int, lcdCount: int, lldCount: int}
     */
    public function statsForCompanyInPeriod(
        int $companyId,
        ?string $periodStart,
        ?string $periodEnd,
    ): array;

    /**
     * Year of the company's first contract (oldest `start_date`). Used
     * to generate the `[firstYear..currentYear]` range for quick-filter
     * year pills.
     *
     * Returns `null` when the company has no contract.
     */
    public function firstContractYearForCompany(int $companyId): ?int;

    /**
     * Sorted, deduplicated list of ISO calendar years during which the
     * company has at least one active contract (even if it covers only
     * part of the year).
     *
     * A company whose only contract runs 15/12/2024 to 10/01/2025 thus
     * returns `[2024, 2025]`.
     *
     * Feeds:
     *   - `availableYears` (populates the local year selector on the
     *     company page)
     *   - `history` iterations (one `CompanyYearStatsData` entry per
     *     year)
     *
     * @return list<int>
     */
    public function findActiveYearsForCompany(int $companyId): array;

    /**
     * Contracts of a company overlapping the `[start, end]` window with
     * `vehicle` eager-loaded (license_plate, brand, model). Pivot of
     * the billing module ({@see App\Services\Billing\BillingCalculator})
     * to enumerate vehicles to bill for a given month.
     *
     * Sort by `vehicle_id` then `start_date` to allow linear grouping
     * server-side (a vehicle may have several successive contracts with
     * the same company in the same month).
     *
     * @return Collection<int, Contract>
     */
    public function findForCompanyInPeriod(
        int $companyId,
        string $startDate,
        string $endDate,
    ): Collection;

    /**
     * Minimal list of active contracts (non soft-deleted) on a given
     * vehicle, projected on `(company_id, start_date, end_date)`. Used
     * by {@see App\Services\Invoice\InvoiceDivergenceFlagger::flagForVehicle}
     * to pivot vehicle → companies + monthly ranges to flag.
     *
     * Returns a `Collection<int, Contract>` (partial Eloquent
     * instances, SoftDeletes scope applied).
     *
     * @return Collection<int, Contract>
     */
    public function findContractDateRangesForVehicle(int $vehicleId): Collection;

    /**
     * For a given company, returns the number of contracts per driver
     * via the N:N pivot `contract_drivers`. A contract with two drivers
     * counts once for each.
     *
     * Return shape: `[driver_id => count]`. Drivers with no contract on
     * this company are absent (not an entry at 0).
     *
     * Used by {@see App\Services\Company\CompanyDetailService::detail}
     * to enrich each driver row with its `contractsCount`.
     *
     * @return array<int, int>
     */
    public function countContractsByDriverForCompany(int $companyId): array;

    /**
     * Global year bounds on non soft-deleted contracts.
     *
     * Source of truth for the dynamic year selector exposed by
     * {@see AvailableYearsResolver}.
     *
     * Returns `['min' => null, 'max' => null]` when the table is empty
     * (lets the resolver handle the fallback to the current year).
     *
     * Implementation note: one raw SQL query with `YEAR(MIN(start_date))`
     * and `YEAR(MAX(start_date))`, explicit `deleted_at IS NULL` filter
     * to bypass the absence of SoftDeletes global scope when querying
     * via `DB::table()`.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function yearBounds(): array;
}
