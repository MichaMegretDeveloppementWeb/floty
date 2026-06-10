<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VehicleEventDetailSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleEventDetailSuggestion>
 */
final class VehicleEventDetailSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(2, true),
        ];
    }
}
