<?php

declare(strict_types=1);

namespace App\Services\Control;

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
}
