<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Recomputes the materialised `vehicles.controls_due_from` cache: per vehicle,
 * the earliest `dueFrom` of its active controls (from
 * {@see FleetControlScheduleScanner}), excluding controls due on or after a
 * planned exit; NULL when none. The live scanner stays the source of truth;
 * this only projects it into a column for SQL filtering.
 */
final readonly class ControlDueDateRecomputeService
{
    /** Caps each `CASE` write statement regardless of fleet size. */
    private const int WRITE_CHUNK = 500;

    public function __construct(
        private FleetControlScheduleScanner $scanner,
        private VehicleReadRepositoryInterface $vehicleRead,
        private VehicleWriteRepositoryInterface $vehicleWrite,
    ) {}

    /** Recomputes every in-fleet vehicle (backfill / nightly self-heal). */
    public function forFleet(): void
    {
        $this->forVehicles($this->vehicleRead->findActiveForReminderScan(CarbonImmutable::today()));
    }

    /** Recomputes one vehicle; no-op if it no longer exists. */
    public function forVehicleId(int $vehicleId): void
    {
        $this->forVehicles($this->vehicleRead->findScheduleColumnsByIds([$vehicleId]));
    }

    /**
     * Recomputes and persists the cache for the given vehicles, in bounded batches.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     */
    public function forVehicles(Collection $vehicles): void
    {
        $dueFromByVehicleId = $this->computeForVehicles($vehicles);

        foreach (array_chunk($dueFromByVehicleId, self::WRITE_CHUNK, true) as $chunk) {
            $this->vehicleWrite->updateControlsDueFrom($chunk);
        }
    }

    /**
     * Expected cache for the in-fleet scope, computed without writing.
     *
     * @return array<int, string|null>
     */
    public function computeForFleet(): array
    {
        return $this->computeForVehicles($this->vehicleRead->findActiveForReminderScan(CarbonImmutable::today()));
    }

    /**
     * Vehicles whose stored cache differs from a fresh recompute, keyed by id
     * (expected vs stored). Empty when consistent. Read-only.
     *
     * @return array<int, array{expected: string|null, stored: string|null}>
     */
    public function detectDrift(): array
    {
        $expected = $this->computeForFleet();
        $stored = $this->vehicleRead->findControlsDueFromByIds(array_keys($expected));

        $drift = [];
        foreach ($expected as $vehicleId => $expectedDueFrom) {
            if (($stored[$vehicleId] ?? null) !== $expectedDueFrom) {
                $drift[$vehicleId] = [
                    'expected' => $expectedDueFrom,
                    'stored' => $stored[$vehicleId] ?? null,
                ];
            }
        }

        return $drift;
    }

    /**
     * Computes the cache value per vehicle (no write), keyed by id (`Y-m-d`|null).
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, string|null>
     */
    public function computeForVehicles(Collection $vehicles): array
    {
        if ($vehicles->isEmpty()) {
            return [];
        }

        $resultsByVehicle = $this->scanner->scanForVehicles($vehicles, CarbonImmutable::today());

        $dueFromByVehicleId = [];
        foreach ($vehicles as $vehicle) {
            $dueFromByVehicleId[$vehicle->id] = $this->earliestDueFrom(
                $resultsByVehicle[$vehicle->id] ?? [],
                $vehicle->exit_date?->toImmutable(),
            )?->toDateString();
        }

        return $dueFromByVehicleId;
    }

    /**
     * Earliest `dueFrom` among the active controls, excluding any due on or
     * after a planned exit. Null when none qualify.
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
