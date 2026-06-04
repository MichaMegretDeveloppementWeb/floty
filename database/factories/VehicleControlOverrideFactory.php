<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\VehicleControlStatus;
use App\Models\ControlDefinition;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleControlOverride>
 */
final class VehicleControlOverrideFactory extends Factory
{
    protected $model = VehicleControlOverride::class;

    /**
     * Default: a vehicle-specific control (no global definition, complete recipe).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'control_definition_id' => null,
            'status' => VehicleControlStatus::Active,
            'name' => 'Contrôle spécifique',
            'anchor' => ControlAnchor::FirstOriginRegistration,
            'initial_duration_value' => 4,
            'initial_duration_unit' => DurationUnit::Years,
            'cycle_value' => 2,
            'cycle_unit' => DurationUnit::Years,
            'notify_driver' => false,
            'implies_unavailability' => false,
            'reminder_days_before' => null,
            'reminder_on_due_day' => null,
            'reminder_repeat_every_days' => null,
            'display_order' => 0,
        ];
    }

    /**
     * A sparse override of a global definition (all recipe fields inherit).
     */
    public function overrideOf(ControlDefinition $definition): self
    {
        return $this->state(fn (): array => [
            'control_definition_id' => $definition->id,
            'name' => null,
            'anchor' => null,
            'initial_duration_value' => null,
            'initial_duration_unit' => null,
            'cycle_value' => null,
            'cycle_unit' => null,
            'notify_driver' => null,
            'implies_unavailability' => null,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => ['status' => VehicleControlStatus::Disabled]);
    }

    public function paused(): self
    {
        return $this->state(fn (): array => ['status' => VehicleControlStatus::Paused]);
    }
}
