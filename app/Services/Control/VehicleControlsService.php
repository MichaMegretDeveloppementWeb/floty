<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
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
 * reminder settings context the editor needs.
 */
final readonly class VehicleControlsService
{
    public function __construct(
        private EffectiveControlResolver $resolver,
        private ControlReminderSettingsReadRepositoryInterface $reminderSettings,
    ) {}

    public function buildForVehicle(Vehicle $vehicle, CarbonImmutable $today): VehicleControlsTabData
    {
        return new VehicleControlsTabData(
            vehicleId: $vehicle->id,
            controls: $this->resolver->resolve($vehicle, $today),
            anchorOptions: EnumOptions::fromCases(ControlAnchor::cases()),
            durationUnitOptions: EnumOptions::fromCases(DurationUnit::cases()),
            statusOptions: EnumOptions::fromCases(VehicleControlStatus::cases()),
            reminderSettings: ControlReminderSettingsData::fromModel(
                $this->reminderSettings->get(),
                $this->reminderSettings->defaultRecipients(),
            ),
        );
    }
}
