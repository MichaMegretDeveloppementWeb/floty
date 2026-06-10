<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VehicleEventNature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleEventNature>
 */
final class VehicleEventNatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(2, true),
            'is_fiscally_reductive' => false,
        ];
    }

    public function reductive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_fiscally_reductive' => true,
        ]);
    }
}
