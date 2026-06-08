<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardControlsDueData;
use App\Data\User\Dashboard\DashboardControlsDueItemData;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\VehicleControlStatus;
use App\Models\ControlDefinition;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\ControlScheduleService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Aggregates the regulatory controls reaching échéance across the active fleet
 * for the Dashboard "Contrôles à échéance" panel.
 *
 * Why PHP, not SQL · the échéance arithmetic must match Carbon (the engine the
 * vehicle "Contrôles" tab and the reminder cron already use). Carbon does NOT
 * clamp month-ends (31 Jan + 1 month = 2 Mar) whereas SQL `DATE_ADD` does
 * (= 29 Feb), so a pure SQL scan would disagree with the rest of the app on
 * any month-end anchor date. The échéance is therefore computed here with the
 * shared {@see ControlScheduleService}, so the panel is consistent by
 * construction.
 *
 * Why batched · the page is a read served on connection, but the data is loaded
 * in a FIXED number of bounded queries (active vehicles + active definitions +
 * executions + overrides + settings), never one query per vehicle. The "due"
 * set mirrors the vehicle-tab badge ({@see VehicleControlsService::dueBadgeForVehicle()}):
 * only Active controls in Overdue / DueToday / DueSoon, out-of-fleet
 * (NotApplicable) controls excluded. An équivalence test pins this batch scan
 * to the per-vehicle resolver so the two never drift.
 */
final readonly class DashboardControlsDueAggregator
{
    private const TOP_ITEMS = 6;

    public function __construct(
        private VehicleReadRepositoryInterface $vehicles,
        private ControlDefinitionReadRepositoryInterface $definitions,
        private VehicleControlOverrideReadRepositoryInterface $overrides,
        private ControlExecutionReadRepositoryInterface $executions,
        private ControlReminderSettingsReadRepositoryInterface $reminderSettings,
        private ControlScheduleService $schedule,
    ) {}

    public function aggregate(CarbonImmutable $today): DashboardControlsDueData
    {
        $vehicles = $this->vehicles->findActiveForReminderScan($today);

        if ($vehicles->isEmpty()) {
            return DashboardControlsDueData::none();
        }

        $vehicleIds = $vehicles->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $defaultDaysBefore = $this->reminderSettings->get()->days_before;
        $definitions = $this->definitions->listActiveForSchedule();
        $executionsByVehicle = $this->executions->latestForVehicles($vehicleIds);
        $overridesByVehicle = $this->overrides->findForVehicles($vehicleIds)->groupBy('vehicle_id');

        /** @var list<DashboardControlsDueItemData> $items */
        $items = [];

        foreach ($vehicles as $vehicle) {
            $exitDate = $vehicle->exit_date?->toImmutable();
            $lastExecutions = $executionsByVehicle[$vehicle->id] ?? [];

            $overridesByDefinition = [];
            $specifics = [];
            foreach ($overridesByVehicle->get($vehicle->id, collect()) as $override) {
                if ($override->control_definition_id !== null) {
                    $overridesByDefinition[$override->control_definition_id] = $override;
                } else {
                    $specifics[] = $override;
                }
            }

            foreach ($definitions as $definition) {
                $item = $this->globalDueItem(
                    $definition,
                    $overridesByDefinition[$definition->id] ?? null,
                    $vehicle,
                    $today,
                    $exitDate,
                    $lastExecutions['def:'.$definition->id] ?? null,
                    $defaultDaysBefore,
                );
                if ($item !== null) {
                    $items[] = $item;
                }
            }

            foreach ($specifics as $override) {
                $item = $this->specificDueItem(
                    $override,
                    $vehicle,
                    $today,
                    $exitDate,
                    $lastExecutions['ovr:'.$override->id] ?? null,
                    $defaultDaysBefore,
                );
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        // Overdue first, then soonest échéance, then license plate for a stable
        // order across runs.
        usort($items, static function (DashboardControlsDueItemData $a, DashboardControlsDueItemData $b): int {
            if ($a->isOverdue !== $b->isOverdue) {
                return $a->isOverdue ? -1 : 1;
            }

            return [$a->nextDueDate, $a->licensePlate] <=> [$b->nextDueDate, $b->licensePlate];
        });

        return new DashboardControlsDueData(
            count: count($items),
            items: array_slice($items, 0, self::TOP_ITEMS),
        );
    }

    /**
     * Due item for a global control resolved against its optional vehicle
     * override, or null when paused/disabled or not needing attention. The
     * schedule coalescing mirrors {@see EffectiveControlResolver::buildFromGlobal()}
     * (pinned by the équivalence test).
     */
    private function globalDueItem(
        ControlDefinition $definition,
        ?VehicleControlOverride $override,
        Vehicle $vehicle,
        CarbonImmutable $today,
        ?CarbonImmutable $exitDate,
        ?CarbonImmutable $lastExecution,
        int $defaultDaysBefore,
    ): ?DashboardControlsDueItemData {
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

        return $this->dueItem(
            $vehicle,
            $override?->name ?? $definition->name,
            $nextDue,
            $this->schedule->deriveStatus($nextDue, $lastExecution, $today, $daysBefore, $exitDate),
        );
    }

    /**
     * Due item for a vehicle-specific control (complete recipe), or null when
     * paused/disabled or not needing attention. Mirrors
     * {@see EffectiveControlResolver::buildFromSpecific()}.
     */
    private function specificDueItem(
        VehicleControlOverride $override,
        Vehicle $vehicle,
        CarbonImmutable $today,
        ?CarbonImmutable $exitDate,
        ?CarbonImmutable $lastExecution,
        int $defaultDaysBefore,
    ): ?DashboardControlsDueItemData {
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

        return $this->dueItem(
            $vehicle,
            (string) $override->name,
            $nextDue,
            $this->schedule->deriveStatus($nextDue, $lastExecution, $today, $daysBefore, $exitDate),
        );
    }

    /**
     * Keeps only the schedule statuses that count as "needs attention" (mirrors
     * the vehicle-tab badge), wrapping the control into a dashboard item.
     */
    private function dueItem(
        Vehicle $vehicle,
        string $controlName,
        CarbonImmutable $nextDue,
        ControlScheduleStatus $scheduleStatus,
    ): ?DashboardControlsDueItemData {
        $isOverdue = $scheduleStatus === ControlScheduleStatus::Overdue;

        if (
            ! $isOverdue
            && $scheduleStatus !== ControlScheduleStatus::DueToday
            && $scheduleStatus !== ControlScheduleStatus::DueSoon
        ) {
            return null;
        }

        return new DashboardControlsDueItemData(
            vehicleId: $vehicle->id,
            licensePlate: (string) $vehicle->license_plate,
            controlName: $controlName,
            nextDueDate: $nextDue->toDateString(),
            scheduleStatus: $scheduleStatus,
            isOverdue: $isOverdue,
        );
    }

    private function anchorDate(Vehicle $vehicle, ControlAnchor $anchor): CarbonImmutable
    {
        /** @var CarbonInterface $date */
        $date = $vehicle->getAttribute($anchor->vehicleColumn());

        return $date->toImmutable();
    }
}
