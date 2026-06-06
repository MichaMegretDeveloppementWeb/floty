<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VehicleEvent;
use App\Models\VehicleEventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleEventCategory>
 */
final class VehicleEventCategoryFactory extends Factory
{
    protected $model = VehicleEventCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_event_id' => VehicleEvent::factory(),
            'category' => fake()->randomElement(['Accident', 'Entretien', 'Fourrière', 'Vol', 'Divers']),
        ];
    }
}
