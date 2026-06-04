<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Models\ControlDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlDefinition>
 */
final class ControlDefinitionFactory extends Factory
{
    protected $model = ControlDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Contrôle technique',
            'anchor' => ControlAnchor::FirstOriginRegistration,
            'initial_duration_value' => 4,
            'initial_duration_unit' => DurationUnit::Years,
            'cycle_value' => 2,
            'cycle_unit' => DurationUnit::Years,
            'notify_driver' => false,
            'implies_unavailability' => true,
            'reminder_days_before' => null,
            'reminder_on_due_day' => null,
            'reminder_repeat_every_days' => null,
            'is_active' => true,
            'display_order' => 0,
        ];
    }

    /**
     * A definition with its own reminder cycle override.
     */
    public function withCustomReminders(): self
    {
        return $this->state(fn (): array => [
            'reminder_days_before' => 30,
            'reminder_on_due_day' => true,
            'reminder_repeat_every_days' => 7,
        ]);
    }

    /**
     * An inactive (paused) definition.
     */
    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
