<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Models\Vehicle;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads on the Vehicle domain.
 *
 * All non-trivial Eloquent queries targeting {@see Vehicle} live here
 * (ADR-0013 R3-bis + R4). Services consume these methods to orchestrate
 * business logic without touching `Vehicle::query()` directly.
 */
interface VehicleReadRepositoryInterface
{
    /**
     * Lists all vehicles for the fleet page, sorted by `acquisition_date
     * DESC` with eager-loaded active fiscal characteristics
     * (`effective_to IS NULL`).
     *
     * @param  bool  $includeExited  When false (default), excludes vehicles
     *                               whose `exit_date` is on or before today
     *                               (ADR-0018 § 4).
     * @return Collection<int, Vehicle>
     *
     * @deprecated Use {@see paginateForIndex()} instead.
     */
    public function findAllForFleetView(bool $includeExited = false): Collection;

    /**
     * Server-side paginated list for the Vehicles Index (ADR-0020).
     * Applies `{search, includeExited, status, sortKey, sortDirection,
     * page, perPage}` from the DTO as raw SQL.
     *
     * Search: LIKE on `license_plate OR brand OR model`.
     * Sort whitelist: licensePlate | model | firstFrenchRegistrationDate
     * | acquisitionDate | currentStatus.
     *
     * Eager-loads active `fiscalCharacteristics` to avoid N+1 on the
     * `fullYearTax` computation downstream.
     *
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginateForIndex(VehicleIndexQueryData $query): LengthAwarePaginator;

    /**
     * Returns true iff at least one vehicle exists (`SELECT EXISTS`).
     * Used by the Index to distinguish an intrinsically empty table from
     * an active filter returning zero rows. Includes exited vehicles.
     */
    public function existsAny(): bool;

    /**
     * Available vehicles (non-exited) for `<SelectInput>` widgets,
     * minimal columns, sorted by license plate.
     *
     * @return Collection<int, Vehicle>
     */
    public function findAllForOptions(): Collection;

    /**
     * Bulk loads vehicles by ids with eager-loaded active fiscal
     * characteristics, indexed by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Vehicle>
     */
    public function findByIdsIndexed(array $ids): Collection;

    /**
     * Nullable unitary lookup without eager-loading. Returns `null` if
     * the id does not exist or the vehicle is soft-deleted.
     *
     * Used by Validation Rules that cannot rely on constructor DI
     * (instantiated via `new` in DTOs) and only need a trivial PK lookup
     * without loading the fiscal chain (ADR-0013 R3).
     */
    public function findById(int $id): ?Vehicle;

    /**
     * Unitary lookup with eager-loaded active fiscal characteristics,
     * throws 404 if the id does not exist.
     */
    public function findOrFailWithFiscal(int $id): Vehicle;

    /**
     * Unitary lookup with eager-loading of all fiscal versions of the
     * vehicle (full history, ordered `effective_from DESC`). Throws 404
     * if the id does not exist.
     *
     * Used by the Show page to compose `VehicleData` with both the
     * current version and the historical timeline.
     */
    public function findByIdWithFiscalHistory(int $id): Vehicle;

    /**
     * Vehicles for the planning heatmap of a given year: includes all
     * vehicles active for at least part of the year (see
     * {@see Vehicle::scopeActiveAt} with `start_of_year`), eager-loads
     * active fiscal characteristics, sorted by license plate.
     *
     * Per ADR-0018 § 4, a vehicle exited mid-year remains visible in the
     * heatmap of the year it was partially active in (cells after
     * exit_date are greyed out client-side).
     *
     * @return Collection<int, Vehicle>
     */
    public function findAllForHeatmap(int $year): Collection;

    /**
     * Counts active vehicles (those with no `exit_date`).
     */
    public function countActive(): int;

    /**
     * Min/max bounds of first French registration years across all
     * vehicles. Used by the Index filter to bound the year selector.
     * Returns `null` when the table is empty.
     *
     * @return array{min: int, max: int}|null
     */
    public function findFirstRegistrationYearBounds(): ?array;
}
