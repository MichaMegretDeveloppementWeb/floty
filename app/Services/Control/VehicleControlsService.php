<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Control\ControlReminderSettingsData;
use App\Data\User\Control\Vehicle\VehicleControlsBadgeData;
use App\Data\User\Control\Vehicle\VehicleControlsTabData;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\VehicleControlStatus;
use App\Models\Vehicle;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;

/**
 * Composes the vehicle "Contrôles" tab payload (Chantier B / B2): the effective
 * controls resolved for the vehicle plus the option lists and the inherited
 * reminder settings context the editor needs. The resolution context (settings,
 * default recipients, catalog) is built once and reused for both the controls
 * and the reminder-settings prop, avoiding a double read.
 */
final readonly class VehicleControlsService
{
    public function __construct(
        private EffectiveControlResolver $resolver,
        private FleetControlScheduleScanner $scanner,
        private VehicleReadRepositoryInterface $vehicles,
    ) {}

    public function buildForVehicle(Vehicle $vehicle, CarbonImmutable $today): VehicleControlsTabData
    {
        $context = $this->resolver->buildContext();

        return new VehicleControlsTabData(
            vehicleId: $vehicle->id,
            controls: $this->resolver->resolveWithContext($vehicle, $today, $context),
            anchorOptions: EnumOptions::fromCases(ControlAnchor::cases()),
            durationUnitOptions: EnumOptions::fromCases(DurationUnit::cases()),
            statusOptions: EnumOptions::fromCases(VehicleControlStatus::cases()),
            reminderSettings: ControlReminderSettingsData::fromModel(
                $context->settings,
                $context->defaultRecipients,
            ),
        );
    }

    /**
     * Eager, lightweight count of the vehicle's controls that need attention,
     * for the tab-label badge. Mirrors the reminder dispatch eligibility:
     * paused/disabled and not-applicable (vehicle left the fleet) controls do
     * not count. Bounded to one vehicle's resolved control set.
     */
    public function dueBadgeForVehicle(Vehicle $vehicle, CarbonImmutable $today): VehicleControlsBadgeData
    {
        $dueCount = 0;
        $overdueCount = 0;

        foreach ($this->resolver->resolve($vehicle, $today) as $control) {
            if ($control->status !== VehicleControlStatus::Active) {
                continue;
            }

            if ($control->scheduleStatus === ControlScheduleStatus::Overdue) {
                $overdueCount++;
                $dueCount++;
            } elseif (
                $control->scheduleStatus === ControlScheduleStatus::DueToday
                || $control->scheduleStatus === ControlScheduleStatus::DueSoon
            ) {
                $dueCount++;
            }
        }

        return new VehicleControlsBadgeData(dueCount: $dueCount, overdueCount: $overdueCount);
    }

    /**
     * Control badges for many vehicles at once (fleet list), keyed by vehicle
     * id. SPARSE: only vehicles with at least one control needing attention are
     * present, so the front renders a badge only where there is one. Resolved
     * by the shared batched scanner (fixed bounded queries, no N+1), same engine
     * as {@see dueBadgeForVehicle()} and the cron. Mirrors that single-vehicle
     * badge's counting (Overdue feeds both counters; DueToday/DueSoon feed only
     * dueCount), pinned by an équivalence test.
     *
     * Currently-exited vehicles are skipped: the fleet badge is an active-fleet
     * attention indicator (like the dashboard panel, which scans active vehicles
     * only), so a vehicle out of the fleet carries no badge even when a control
     * was overdue before it left.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, VehicleControlsBadgeData>
     */
    public function badgesForVehicleIds(array $vehicleIds, CarbonImmutable $today): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $vehicles = $this->vehicles->findScheduleColumnsByIds($vehicleIds)->filter(
            static fn (Vehicle $vehicle): bool => $vehicle->exit_date === null
                || $vehicle->exit_date->toImmutable()->greaterThan($today),
        );
        $resultsByVehicle = $this->scanner->scanForVehicles($vehicles, $today);

        $badges = [];

        foreach ($vehicles as $vehicle) {
            $dueCount = 0;
            $overdueCount = 0;

            foreach ($resultsByVehicle[$vehicle->id] ?? [] as $result) {
                if ($result->scheduleStatus === ControlScheduleStatus::Overdue) {
                    $overdueCount++;
                    $dueCount++;
                } elseif (
                    $result->scheduleStatus === ControlScheduleStatus::DueToday
                    || $result->scheduleStatus === ControlScheduleStatus::DueSoon
                ) {
                    $dueCount++;
                }
            }

            if ($dueCount > 0) {
                $badges[$vehicle->id] = new VehicleControlsBadgeData(dueCount: $dueCount, overdueCount: $overdueCount);
            }
        }

        return $badges;
    }
}
