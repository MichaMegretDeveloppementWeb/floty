<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

use App\Models\VehicleEvent;
use App\Services\VehicleEvent\VehicleEventQueryService;
use Illuminate\Support\Collection;

/**
 * Reads on the VehicleEvent domain.
 *
 * No transformation, no DTO composition (R3) · returns raw Model
 * collections or primitive arrays. Composition lives in
 * {@see VehicleEventQueryService}.
 */
interface VehicleEventReadRepositoryInterface
{
    /**
     * All unavailabilities of a vehicle (excluding soft-deleted),
     * sorted by `start_date DESC` for reverse-chronological display.
     *
     * @return Collection<int, VehicleEvent>
     */
    public function findForVehicle(int $vehicleId): Collection;

    /**
     * Unavailabilities for several vehicles in one `SELECT IN` · returns
     * a map `vehicleId → list<VehicleEvent>` (vehicles without any
     * unavailability are absent from the map; callers default to `[]`).
     *
     * Replaces the N+1 anti-pattern of a PHP loop calling
     * {@see self::findForVehicle()} for each id.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, list<VehicleEvent>>
     */
    public function findForVehicleIds(array $vehicleIds): array;

    /**
     * Unitary lookup · throws 404 if the id does not exist.
     */
    public function findById(int $id): VehicleEvent;

    /**
     * Distinct categories already used across all events, ascending. Feeds the
     * autocomplete suggestions (merged with the predefined seed on the front)
     * and, later, the category filter facets.
     *
     * @return list<string>
     */
    public function distinctCategories(): array;

    /**
     * Single event scoped to its vehicle, with `documents` eager-loaded,
     * for the dedicated detail / edit pages. Throws 404 when the id does
     * not exist OR does not belong to `$vehicleId` (URL consistency guard).
     */
    public function findForVehicleDetail(int $vehicleId, int $vehicleEventId): VehicleEvent;

    /**
     * Days of unavailability (all types) per ISO week of the year.
     * Used to size a stacked "unavailable" segment above contracts in
     * the 52-week timeline of the vehicle page.
     *
     * An unavailability covering 3 days of a week returns `[N => 3]`
     * (not the whole week like an overlay).
     *
     * @return array<int, int> weekNumber (1-53) → unavailable days (1-7)
     */
    public function findUnavailableDaysByWeekForVehicle(int $vehicleId, int $year): array;

    /**
     * Unavailabilities of a vehicle whose `[start_date, end_date]`
     * range extends past the given date · i.e. `end_date > $date` or
     * `end_date IS NULL` (still-open unavailability).
     *
     * Used by {@see App\Services\Vehicle\VehicleExitImpactComputer} to
     * enumerate conflicts blocking a proposed fleet exit at `$date`
     * (ADR-0018 § 8.1).
     *
     * @return Collection<int, VehicleEvent>
     */
    public function findActiveOverlappingDateForVehicle(int $vehicleId, string $date): Collection;
}
