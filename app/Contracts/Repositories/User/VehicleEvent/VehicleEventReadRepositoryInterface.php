<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

use App\Data\User\VehicleEvent\VehicleEventIndexQueryData;
use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Models\VehicleEvent;
use App\Services\VehicleEvent\VehicleEventQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * The system-generated lifecycle event of a given kind (acquisition / fleet
     * exit) for a vehicle, or null when none exists. Lets the recorder preserve
     * the attached cost when re-syncing a read-only lifecycle marker.
     */
    public function findSystemEventForVehicle(int $vehicleId, VehicleEventSystemKind $kind): ?VehicleEvent;

    /**
     * Global vehicle-events index · slim, filtered, sorted, paginated (all
     * vehicles). Eager-loads `vehicle:id,license_plate` + `categories`.
     *
     * NE PAS réutiliser pour la fiche détail ni la timeline véhicule (qui ont
     * leurs propres méthodes `findForVehicle` / `findForVehicleDetail`).
     *
     * @return LengthAwarePaginator<int, VehicleEvent>
     */
    public function paginateForIndex(VehicleEventIndexQueryData $query): LengthAwarePaginator;

    /**
     * Sum of `amount_cents` (costs) over the SAME filtered set as
     * {@see paginateForIndex} (all matching rows, not just the current page).
     * One SQL `SUM`; powers the "Total" stat of the index.
     */
    public function sumAmountForIndex(VehicleEventIndexQueryData $query): int;

    /**
     * Distinct calendar years of events (by `start_date`), descending, for the
     * year filter.
     *
     * @return list<int>
     */
    public function distinctEventYears(): array;

    /**
     * Whether any vehicle event exists at all · drives the global index empty
     * state placeholder (vs « no result for these filters »).
     */
    public function existsAnyVehicleEvent(): bool;

    /**
     * Distinct natures actually attached to events, ascending. Feeds the
     * « Nature » filter suggestions of the global index (real values present,
     * unlike the form suggestions which come from the catalogue).
     *
     * @return list<string>
     */
    public function distinctCategories(): array;

    /**
     * Distinct garage names recorded on events, ascending. Feeds the form
     * autocomplete, which grows automatically with every saved event.
     *
     * @return list<string>
     */
    public function distinctGarages(): array;

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
