<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Models\ControlExecution;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads of control executions (Chantier B / B2).
 */
interface ControlExecutionReadRepositoryInterface
{
    /**
     * Latest execution date per control target for a vehicle, keyed by
     * `def:{id}` (global definition) or `ovr:{id}` (vehicle-specific override).
     * One aggregate query per vehicle (no full load).
     *
     * @return array<string, CarbonImmutable>
     */
    public function latestForVehicle(int $vehicleId): array;

    /**
     * Latest execution date per control target for many vehicles at once (batch
     * variant of {@see latestForVehicle()} for fleet-wide scans), keyed by
     * vehicle id then by `def:{id}` / `ovr:{id}`. One aggregate query total.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, array<string, CarbonImmutable>>
     */
    public function latestForVehicles(array $vehicleIds): array;

    /**
     * Execution history (most recent first, with documents) for one control
     * target on a vehicle.
     *
     * @return Collection<int, ControlExecution>
     */
    public function historyForControl(int $vehicleId, ?int $definitionId, ?int $overrideId): Collection;

    public function findById(int $id): ?ControlExecution;
}
