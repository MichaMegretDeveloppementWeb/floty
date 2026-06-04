<?php

declare(strict_types=1);

namespace App\Data\User\Control\Vehicle;

use App\Data\User\Control\ControlReminderSettingsData;
use App\Data\User\Vehicle\EnumOptionData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload of the vehicle "Contrôles" tab (Chantier B / B2): the effective
 * controls for the vehicle plus the option lists and the inherited reminder
 * settings context the editor needs (mirrors the B1 catalog payload).
 */
#[TypeScript]
final class VehicleControlsTabData extends Data
{
    /**
     * @param  array<int, EffectiveControlData>  $controls
     * @param  array<int, EnumOptionData>  $anchorOptions
     * @param  array<int, EnumOptionData>  $durationUnitOptions
     * @param  array<int, EnumOptionData>  $statusOptions
     */
    public function __construct(
        public int $vehicleId,
        #[DataCollectionOf(EffectiveControlData::class)]
        public array $controls,
        #[DataCollectionOf(EnumOptionData::class)]
        public array $anchorOptions,
        #[DataCollectionOf(EnumOptionData::class)]
        public array $durationUnitOptions,
        #[DataCollectionOf(EnumOptionData::class)]
        public array $statusOptions,
        public ControlReminderSettingsData $reminderSettings,
    ) {}
}
