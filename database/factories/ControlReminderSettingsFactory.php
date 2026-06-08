<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ControlReminderSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlReminderSettings>
 */
final class ControlReminderSettingsFactory extends Factory
{
    protected $model = ControlReminderSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'days_before' => 15,
            'remind_on_due_day' => true,
            'repeat_every_days' => 5,
        ];
    }
}
