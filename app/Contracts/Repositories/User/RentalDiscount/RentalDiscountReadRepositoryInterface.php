<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\RentalDiscount;

use App\Data\User\RentalDiscount\RentalDiscountIndexQueryData;
use App\Models\RentalDiscount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads on commercial rental discounts applied to rents.
 *
 * Consumed by:
 *   - `RentalDiscountConflictService` for overlap validation
 *   - `DiscountResolver` for batch preloading during invoice
 *     computation
 *   - `RentalDiscountQueryService` for the Index/Show views
 *   - `Vehicle*Repository` for the "vehicle listed in an active
 *     discount" check before deletion
 */
interface RentalDiscountReadRepositoryInterface
{
    /**
     * Retrieves a discount by id with its attached vehicles
     * (eager-load `vehicles`). Returns `null` if not found or
     * soft-deleted.
     */
    public function findById(int $id): ?RentalDiscount;

    /**
     * Variant including soft-deleted · for audit views / details of
     * emitted invoices referencing an archived discount.
     */
    public function findByIdWithTrashed(int $id): ?RentalDiscount;

    /**
     * Returns true iff at least one non soft-deleted discount exists.
     * Feeds the `hasAnyRentalDiscount` flag exposed in the Inertia
     * payload to distinguish empty state from "active filter without
     * results".
     */
    public function existsAny(): bool;

    /**
     * All active or planned discounts of a company crossing the given
     * year (start_date <= 31/12/Y and end_date >= 01/01/Y). Eager-loads
     * `vehicles`. Sorted by `start_date` ascending.
     *
     * Used by `DiscountResolver` for batch preloading.
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findActiveForCompanyYear(int $companyId, int $year): Collection;

    /**
     * Multi-company variant · one SQL query for N companies on the
     * given year. Eager-loads `vehicles`.
     *
     * Used by `DiscountResolver` during the Dashboard batch
     * `totalRecettesForYears` (cross-companies × cross-years).
     *
     * @param  list<int>  $companyIds
     * @return Collection<int, RentalDiscount>
     */
    public function findActiveForCompaniesYear(array $companyIds, int $year): Collection;

    /**
     * Returns the company's discounts whose period overlaps
     * `[startDate, endDate]` (inclusive). Eager-loads `vehicles`.
     * `excludeId` allows excluding a discount being edited.
     *
     * Used by `RentalDiscountConflictService`. The vehicle intersection
     * filter is done in PHP server-side (the pivot is small,
     * typically < 50 vehicles).
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findOverlappingForCompany(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Returns the discounts active at a given date that explicitly
     * list the given vehicle (pivot non-empty AND vehicle_id is in
     * it). Feeds the "is this vehicle blocked for deletion?" check
     * (cf. `VehicleInUseByDiscountException`).
     *
     * Note: "all vehicles" discounts (empty pivot) do NOT block
     * vehicle deletion, because the vehicle is not referenced there ·
     * its deletion silently shrinks the effective perimeter (consistent
     * with the "all current vehicles" semantic).
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findActiveListingVehicleOn(int $vehicleId, string $date): Collection;

    /**
     * All non soft-deleted discounts of a company, eager-loading
     * `vehicles` + `company`. Sorted by `start_date` descending
     * (newest first, per Index UI convention). Feeds the "Commercial
     * discounts" section of the Billing tab on Company Show.
     *
     * @return Collection<int, RentalDiscount>
     */
    public function findForCompany(int $companyId): Collection;

    /**
     * Server-side paginated Index (ADR-0020). Applies filters
     * (companyId, status, search) and a whitelisted sort in raw SQL.
     * Eager-loads `vehicles` + `company` to feed
     * `RentalDiscountListItemData`.
     *
     * @return LengthAwarePaginator<int, RentalDiscount>
     */
    public function paginateForIndex(RentalDiscountIndexQueryData $query): LengthAwarePaginator;

    /**
     * Show detail · eager-loads `vehicles` + `company` + `createdBy`.
     */
    public function findByIdForShow(int $id): ?RentalDiscount;

    /**
     * Counters for the stats banner on the Index ·
     * `{active, planned, expired}` relative to today.
     *
     * @return array{active: int, planned: int, expired: int}
     */
    public function statsForIndex(string $today): array;
}
