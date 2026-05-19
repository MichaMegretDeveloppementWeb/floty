<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Driver;

use App\Data\User\Driver\DriverIndexQueryData;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Pivot\DriverCompany;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Driver reads · slim interface per ADR-0013.
 *
 * Zero transformation, zero business decision · returns raw Models /
 * Collections. DTO composition lives in
 * {@see DriverQueryService}.
 */
interface DriverReadRepositoryInterface
{
    public function findById(int $id): ?Driver;

    /**
     * Driver with company memberships + contract counts for the Show
     * page.
     */
    public function findByIdWithRelations(int $id): ?Driver;

    /**
     * Server-side paginated list for the Drivers Index (ADR-0020).
     * Applies `{search, sortKey, sortDirection, page, perPage}` from
     * the DTO as raw SQL via `where`/`orderBy`/`paginate`.
     *
     * Eager-loads active memberships + contracts counts + active
     * companies count to allow sorting by `contractsCount` and
     * `activeCompaniesCount`.
     *
     * @return LengthAwarePaginator<int, Driver>
     */
    public function paginateForIndex(DriverIndexQueryData $query): LengthAwarePaginator;

    /**
     * Returns true iff at least one driver exists (`SELECT EXISTS`).
     * Used by the Index to distinguish an intrinsically empty table
     * from an active filter returning zero rows.
     */
    public function existsAny(): bool;

    /**
     * Flat list of all drivers (sorted by name/firstname). Used by the
     * driver filter of the Contracts Index, which is not restricted to
     * a company / period.
     *
     * @return Collection<int, Driver>
     */
    public function listAllForOptions(): Collection;

    /**
     * Drivers with at least one membership (active or historical) with
     * the given company. Used by the Drivers section on the Company
     * Show page.
     *
     * @return Collection<int, Driver>
     */
    public function listForCompany(int $companyId, bool $includeInactive = true): Collection;

    /**
     * Drivers active in the company over the whole period [start, end]:
     * `joined_at <= start AND (left_at IS NULL OR left_at >= end)`.
     * Used by the driver picker on the Contract form.
     *
     * @return Collection<int, Driver>
     */
    public function listActiveInCompanyDuring(
        int $companyId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection;

    /**
     * Loads all drivers with at least one membership (active or past)
     * in the given company, with their memberships eager-loaded (pivot
     * `driver_company` filtered on this company only).
     *
     * Batch variant for services that need to apply in memory a filter
     * `joined_at <= X AND (left_at IS NULL OR left_at >= Y)` for
     * several (X, Y) pairs in the same request (e.g.
     * `DriverQueryService::futureContractsForLeavePreview` iterates on
     * N future contracts and used to call N times
     * `listActiveInCompanyDuring`).
     *
     * @return Collection<int, Driver>
     */
    public function listAllInCompanyWithMemberships(int $companyId): Collection;

    /**
     * Counts the driver's upcoming contracts (`start_date > leftAt`) in
     * the given company. Used by the "set left_at" workflow.
     */
    public function countFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): int;

    /**
     * Lists the driver's upcoming contracts (`start_date > leftAt`) in
     * the given company, to expose them in the leave modal.
     *
     * @return Collection<int, Contract>
     */
    public function listFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): Collection;

    /**
     * Counts the driver's contracts grouped by company (single query).
     * Used by the driver detail page to populate memberships without
     * N+1.
     *
     * @return array<int, int> Map `[companyId => count]`
     */
    public function countContractsForDriverGroupedByCompany(int $driverId): array;

    /**
     * Retrieves the most recent active membership of the driver in the
     * given company. Used by the leave workflow.
     */
    public function findActiveMembership(int $driverId, int $companyId): ?DriverCompany;

    /**
     * Retrieves a membership by its pivot id. Used by detachment.
     */
    public function findMembershipById(int $pivotId): ?DriverCompany;

    /**
     * Counts the total number of contracts referencing this driver
     * (all companies, all periods). Used by the pre-deletion check.
     */
    public function countContractsForDriver(int $driverId): int;
}
