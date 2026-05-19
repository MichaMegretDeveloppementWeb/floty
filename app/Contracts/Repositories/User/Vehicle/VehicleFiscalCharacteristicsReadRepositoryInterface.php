<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Fiscal\ValueObjects\VfcEffectiveSegment;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

/**
 * Reads on the historical fiscal characteristics of a vehicle.
 *
 * Used by the fiscal engine to resolve the active period at a given
 * instant. Separated from {@see VehicleReadRepositoryInterface}
 * because the fiscal engine does not depend on the Vehicle as a whole,
 * only on the current fiscal period.
 */
interface VehicleFiscalCharacteristicsReadRepositoryInterface
{
    /**
     * Current fiscal characteristics (`effective_to IS NULL`, latest
     * by `effective_from`) of a vehicle. Returns `null` if the vehicle
     * has no active period.
     */
    public function findCurrentForVehicle(Vehicle $vehicle): ?VehicleFiscalCharacteristics;

    /**
     * All VFC segments effective during the given fiscal year, sorted
     * by `start ASC`. Bounds clipped to `[year-01-01, year-12-31]`
     * (inclusive).
     *
     * A VFC is included iff `effective_from <= year-12-31` AND
     * (`effective_to IS NULL` OR `effective_to >= year-01-01`).
     *
     * Returns an empty list when no VFC is active over the year
     * (vehicle created after the computed year for example). It is up
     * to the caller ({@see App\Fiscal\Pipeline\FiscalSegmentedExecutor})
     * to decide what to do · typically throw
     * `FiscalCalculationException::missingFiscalCharacteristics`.
     *
     * Always issues a SQL query: most eager-loads in the project are
     * restricted to `effective_to IS NULL`, so a `relationLoaded()`
     * shortcut would silently mask historical VFCs on multi-VFC
     * vehicles.
     *
     * To batch the read on N vehicles in the same context (Index
     * pages, aggregator prewarm), prefer
     * {@see findEffectiveSegmentsForYearBatch} which collapses N+1
     * queries into a single one.
     *
     * @return list<VfcEffectiveSegment>
     */
    public function findEffectiveSegmentsForYear(Vehicle $vehicle, int $year): array;

    /**
     * Batch variant of {@see findEffectiveSegmentsForYear} · loads in a
     * single SQL query the VFC segments effective over the year for
     * all `$vehicleIds`, and returns a map
     * `vehicleId → list<VfcEffectiveSegment>` (each list sorted by
     * `start ASC`).
     *
     * Use case: the Contracts Index has N distinct vehicles whose full
     * year tax must be computed · instead of N queries (one per
     * vehicle), one query is issued.
     *
     * Equivalence guarantee: for any `vehicleId` present in
     * `$vehicleIds`, the result is strictly identical to
     * `findEffectiveSegmentsForYear($vehicle, $year)`. Covered by
     * `VehicleFiscalCharacteristicsReadRepositoryTest::batch_equivalent_a_appel_individuel`.
     *
     * Vehicles without any VFC over the year appear in the map with an
     * empty list.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, list<VfcEffectiveSegment>>
     */
    public function findEffectiveSegmentsForYearBatch(array $vehicleIds, int $year): array;

    /**
     * Latest VFC of a vehicle whose `effective_from` is strictly before
     * the given date. Used to adjust the `effective_to` bound of the
     * version preceding a new version inserted at `$date`.
     */
    public function findLastVersionStrictlyBefore(
        int $vehicleId,
        DateTimeInterface $date,
    ): ?VehicleFiscalCharacteristics;

    /**
     * Unitary lookup · throws 404 if the id does not exist.
     */
    public function findById(int $id): VehicleFiscalCharacteristics;

    /**
     * VFC immediately adjacent (by `effective_from` order) to a given
     * VFC · either the previous one (`direction = -1`) or the next one
     * (`direction = +1`). Used to fill gaps created by bound
     * modification or deletion.
     *
     * Returns `null` if the given VFC is respectively the first or the
     * last in the history.
     *
     * @param  -1|1  $direction
     */
    public function findAdjacent(
        VehicleFiscalCharacteristics $vfc,
        int $direction,
    ): ?VehicleFiscalCharacteristics;

    /**
     * Counts the VFCs of a vehicle. Used by the deletion Action to
     * block deleting the only VFC.
     */
    public function countForVehicle(int $vehicleId): int;

    /**
     * All VFCs of a vehicle except one (by id), sorted by
     * `effective_from ASC`. Used by the edit Action to compute impacts
     * on neighbours (cf.
     * {@see App\Services\Vehicle\FiscalCharacteristicsImpactComputer}).
     *
     * @return list<VehicleFiscalCharacteristics>
     */
    public function findOthersForVehicle(int $vehicleId, int $excludeId): array;
}
