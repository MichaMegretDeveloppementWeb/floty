<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ControlDefinition;
use App\Models\ControlExecution;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlExecution>
 */
final class ControlExecutionFactory extends Factory
{
    protected $model = ControlExecution::class;

    /**
     * Default: an execution against a global definition.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'control_definition_id' => ControlDefinition::factory(),
            'vehicle_control_override_id' => null,
            'vehicle_event_id' => null,
            'executed_on' => '2024-06-01',
            'note' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
