<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\VehicleControlStatus;
use App\Models\ControlDefinition;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves the échéance (next due date + schedule status) of every ACTIVE
 * regulatory control for a set of vehicles, in a FIXED, bounded number of
 * batched queries (active definitions + reminder settings + executions +
 * overrides), never one query per vehicle.
 *
 * Single source of truth for fleet-wide control surfacing (dashboard panel,
 * fleet-list badges). The échéance is computed in PHP with the shared
 * {@see ControlScheduleService} so it matches the per-vehicle resolver and the
 * reminder cron exactly: Carbon does NOT clamp month-ends (31 Jan + 1 month =
 * 2 Mar) whereas SQL `DATE_ADD` does (= 29 Feb), so a SQL scan would disagree
 * with the rest of the app on month-end anchor dates. The schedule coalescing
 * (override → definition) mirrors {@see EffectiveControlResolver}; both are
 * pinned to it by équivalence tests.
 *
 * Paused / disabled controls are dropped (no result), like the cron and the
 * vehicle-tab badge. Schedule-only: recipient cascades are never loaded.
 */
final readonly class FleetControlScheduleScanner
{
    public function __construct(
        private ControlDefinitionReadRepositoryInterface $definitions,
        private VehicleControlOverrideReadRepositoryInterface $overrides,
        private ControlExecutionReadRepositoryInterface $executions,
        private ControlReminderSettingsReadRepositoryInterface $reminderSettings,
        private ControlScheduleService $schedule,
    ) {}

    /**
     * @param  iterable<Vehicle>  $vehicles  models carrying the anchor date columns + exit_date
     * @return array<int, list<VehicleControlScheduleResult>> keyed by vehicle id (only Active controls)
     */
    public function scanForVehicles(iterable $vehicles, CarbonImmutable $today): array
    {
        $vehicles = collect($vehicles);

        if ($vehicles->isEmpty()) {
            return [];
        }

        $vehicleIds = $vehicles->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $defaultDaysBefore = $this->reminderSettings->get()->days_before;
        $definitions = $this->definitions->listActiveForSchedule();
        $executionsByVehicle = $this->executions->latestForVehicles($vehicleIds);
        $overridesByVehicle = $this->overrides->findForVehicles($vehicleIds)->groupBy('vehicle_id');

        $byVehicle = [];

        foreach ($vehicles as $vehicle) {
            $byVehicle[$vehicle->id] = $this->resolveVehicle(
                $vehicle,
                $today,
                $definitions,
                $executionsByVehicle[$vehicle->id] ?? [],
                $overridesByVehicle->get($vehicle->id, collect()),
                $defaultDaysBefore,
            );
        }

        return $byVehicle;
    }

    /**
     * @param  iterable<ControlDefinition>  $definitions
     * @param  array<string, CarbonImmutable>  $lastExecutions
     * @param  iterable<VehicleControlOverride>  $overrides
     * @return list<VehicleControlScheduleResult>
     */
    private function resolveVehicle(
        Vehicle $vehicle,
        CarbonImmutable $today,
        iterable $definitions,
        array $lastExecutions,
        iterable $overrides,
        int $defaultDaysBefore,
    ): array {
        $exitDate = $vehicle->exit_date?->toImmutable();

        $overridesByDefinition = [];
        $specifics = [];
        foreach ($overrides as $override) {
            if ($override->control_definition_id !== null) {
                $overridesByDefinition[$override->control_definition_id] = $override;
            } else {
                $specifics[] = $override;
            }
        }

        $results = [];

        foreach ($definitions as $definition) {
            $result = $this->globalResult(
                $definition,
                $overridesByDefinition[$definition->id] ?? null,
                $vehicle,
                $today,
                $exitDate,
                $lastExecutions['def:'.$definition->id] ?? null,
                $defaultDaysBefore,
            );
            if ($result !== null) {
                $results[] = $result;
            }
        }

        foreach ($specifics as $override) {
            $result = $this->specificResult(
                $override,
                $vehicle,
                $today,
                $exitDate,
                $lastExecutions['ovr:'.$override->id] ?? null,
                $defaultDaysBefore,
            );
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Échéance of a global control resolved against its optional vehicle
     * override, or null when paused/disabled. Coalescing mirrors
     * {@see EffectiveControlResolver::buildFromGlobal()}.
     */
    private function globalResult(
        ControlDefinition $definition,
        ?VehicleControlOverride $override,
        Vehicle $vehicle,
        CarbonImmutable $today,
        ?CarbonImmutable $exitDate,
        ?CarbonImmutable $lastExecution,
        int $defaultDaysBefore,
    ): ?VehicleControlScheduleResult {
        $status = $override?->status ?? VehicleControlStatus::Active;
        if ($status !== VehicleControlStatus::Active) {
            return null;
        }

        $nextDue = $this->schedule->nextDueDate(
            $this->anchorDate($vehicle, $override?->anchor ?? $definition->anchor),
            $override?->initial_duration_value ?? $definition->initial_duration_value,
            $override?->initial_duration_unit ?? $definition->initial_duration_unit,
            $override?->cycle_value ?? $definition->cycle_value,
            $override?->cycle_unit ?? $definition->cycle_unit,
            $lastExecution,
        );

        $daysBefore = $override?->reminder_days_before
            ?? $definition->reminder_days_before
            ?? $defaultDaysBefore;

        return new VehicleControlScheduleResult(
            controlName: $override?->name ?? $definition->name,
            nextDue: $nextDue,
            scheduleStatus: $this->schedule->deriveStatus($nextDue, $lastExecution, $today, $daysBefore, $exitDate),
        );
    }

    /**
     * Échéance of a vehicle-specific control (complete recipe), or null when
     * paused/disabled. Mirrors {@see EffectiveControlResolver::buildFromSpecific()}.
     */
    private function specificResult(
        VehicleControlOverride $override,
        Vehicle $vehicle,
        CarbonImmutable $today,
        ?CarbonImmutable $exitDate,
        ?CarbonImmutable $lastExecution,
        int $defaultDaysBefore,
    ): ?VehicleControlScheduleResult {
        if ($override->status !== VehicleControlStatus::Active) {
            return null;
        }

        $nextDue = $this->schedule->nextDueDate(
            $this->anchorDate($vehicle, $override->anchor),
            (int) $override->initial_duration_value,
            $override->initial_duration_unit,
            (int) $override->cycle_value,
            $override->cycle_unit,
            $lastExecution,
        );

        $daysBefore = $override->reminder_days_before ?? $defaultDaysBefore;

        return new VehicleControlScheduleResult(
            controlName: (string) $override->name,
            nextDue: $nextDue,
            scheduleStatus: $this->schedule->deriveStatus($nextDue, $lastExecution, $today, $daysBefore, $exitDate),
        );
    }

    private function anchorDate(Vehicle $vehicle, ControlAnchor $anchor): CarbonImmutable
    {
        /** @var CarbonInterface $date */
        $date = $vehicle->getAttribute($anchor->vehicleColumn());

        return $date->toImmutable();
    }
}
