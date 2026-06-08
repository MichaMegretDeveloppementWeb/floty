<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlExecutionReadRepositoryInterface;
use App\Models\ControlExecution;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class ControlExecutionReadRepository implements ControlExecutionReadRepositoryInterface
{
    /**
     * @return array<string, CarbonImmutable>
     */
    public function latestForVehicle(int $vehicleId): array
    {
        $rows = ControlExecution::query()
            ->where('vehicle_id', $vehicleId)
            ->selectRaw('control_definition_id, vehicle_control_override_id, MAX(executed_on) as last_executed')
            ->groupBy('control_definition_id', 'vehicle_control_override_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $key = $row->control_definition_id !== null
                ? 'def:'.$row->control_definition_id
                : 'ovr:'.$row->vehicle_control_override_id;

            $map[$key] = CarbonImmutable::parse((string) $row->getAttribute('last_executed'));
        }

        return $map;
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return array<int, array<string, CarbonImmutable>>
     */
    public function latestForVehicles(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $rows = ControlExecution::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->selectRaw('vehicle_id, control_definition_id, vehicle_control_override_id, MAX(executed_on) as last_executed')
            ->groupBy('vehicle_id', 'control_definition_id', 'vehicle_control_override_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $key = $row->control_definition_id !== null
                ? 'def:'.$row->control_definition_id
                : 'ovr:'.$row->vehicle_control_override_id;

            $map[(int) $row->vehicle_id][$key] = CarbonImmutable::parse((string) $row->getAttribute('last_executed'));
        }

        return $map;
    }

    /**
     * @return Collection<int, ControlExecution>
     */
    public function historyForControl(int $vehicleId, ?int $definitionId, ?int $overrideId): Collection
    {
        return ControlExecution::query()
            ->where('vehicle_id', $vehicleId)
            ->when($definitionId !== null, fn ($query) => $query->where('control_definition_id', $definitionId))
            ->when($overrideId !== null, fn ($query) => $query->where('vehicle_control_override_id', $overrideId))
            ->with('documents')
            ->orderByDesc('executed_on')
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int $id): ?ControlExecution
    {
        return ControlExecution::query()->find($id);
    }
}
