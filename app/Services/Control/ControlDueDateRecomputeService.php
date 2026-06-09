<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Recomputes and persists the materialised `vehicles.controls_due_from` cache:
 * the earliest date from which a vehicle has at least one active control
 * needing attention. This is a DERIVED value: the live computation
 * ({@see FleetControlScheduleScanner}, itself backed by
 * {@see ControlScheduleService}) stays the single source of truth, and this
 * service merely projects its result into a column so the fleet list can
 * filter "controls due" in plain SQL.
 *
 * The column equals, per vehicle, the MIN over its ACTIVE controls of
 * `dueFrom` (= next due minus the effective reminder window), restricted to
 * controls that do NOT fall on or after a planned exit (mirroring
 * {@see ControlScheduleService::deriveStatus()} `nextDue >= exitDate →
 * NotApplicable`). The "exit already happened" case (`exit_date <= today`) is
 * today-relative and is handled by the index filter's `WHERE`, not baked here.
 * NULL = no active control / never due.
 *
 * Recompute is event-driven (observers on the control + vehicle models) for
 * freshness, fully replayed nightly for self-healing, and drift-checked. See
 * the recompute / verify Artisan commands.
 */
final readonly class ControlDueDateRecomputeService
{
    /**
     * Batch size for the materialisation write, keeping each `CASE` update
     * statement bounded regardless of fleet size.
     */
    private const int WRITE_CHUNK = 500;

    public function __construct(
        private FleetControlScheduleScanner $scanner,
        private VehicleReadRepositoryInterface $vehicleRead,
        private VehicleWriteRepositoryInterface $vehicleWrite,
    ) {}

    /**
     * Recompute the whole in-fleet scope (active + planned-future-exit
     * vehicles). Used for the one-off backfill and the nightly self-heal.
     */
    public function forFleet(): void
    {
        $this->forVehicles($this->vehicleRead->findActiveForReminderScan(CarbonImmutable::today()));
    }

    /**
     * Recompute a single vehicle (observer path). No-op if it no longer exists.
     */
    public function forVehicleId(int $vehicleId): void
    {
        $this->forVehicles($this->vehicleRead->findScheduleColumnsByIds([$vehicleId]));
    }

    /**
     * Recompute the cache for a set of vehicles already carrying their anchor +
     * exit_date columns, persisting in bounded batches.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     */
    public function forVehicles(Collection $vehicles): void
    {
        if ($vehicles->isEmpty()) {
            return;
        }

        $resultsByVehicle = $this->scanner->scanForVehicles($vehicles, CarbonImmutable::today());

        $dueFromByVehicleId = [];
        foreach ($vehicles as $vehicle) {
            $dueFromByVehicleId[$vehicle->id] = $this->earliestDueFrom(
                $resultsByVehicle[$vehicle->id] ?? [],
                $vehicle->exit_date?->toImmutable(),
            )?->toDateString();
        }

        foreach (array_chunk($dueFromByVehicleId, self::WRITE_CHUNK, true) as $chunk) {
            $this->vehicleWrite->updateControlsDueFrom($chunk);
        }
    }

    /**
     * Earliest `dueFrom` among a vehicle's active control results, excluding
     * controls falling on or after a planned exit (those are NotApplicable).
     * Null when no qualifying control remains.
     *
     * @param  list<VehicleControlScheduleResult>  $results
     */
    private function earliestDueFrom(array $results, ?CarbonImmutable $exitDate): ?CarbonImmutable
    {
        $earliest = null;
        foreach ($results as $result) {
            if ($exitDate !== null && $result->nextDue->greaterThanOrEqualTo($exitDate)) {
                continue;
            }
            if ($earliest === null || $result->dueFrom->lessThan($earliest)) {
                $earliest = $result->dueFrom;
            }
        }

        return $earliest;
    }
}
