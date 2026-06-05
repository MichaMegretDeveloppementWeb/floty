<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Data\User\Control\ControlReminderSettingsData;
use App\Data\User\Control\Vehicle\VehicleControlsTabData;
use App\Enums\Control\ControlAnchor;
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
}
