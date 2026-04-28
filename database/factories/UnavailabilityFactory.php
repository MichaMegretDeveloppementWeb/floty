<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Unavailability;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unavailability>
 */
final class UnavailabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(UnavailabilityType::cases());

        $start = fake()->dateTimeBetween('-1 year', 'now');
        $end = (clone $start)->modify('+'.fake()->numberBetween(1, 14).' days');

        return [
            'vehicle_id' => Vehicle::factory(),
            'type' => $type,
            'has_fiscal_impact' => $type->hasFiscalImpact(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function pound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UnavailabilityType::Pound,
            'has_fiscal_impact' => true,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'end_date' => null,
        ]);
    }
}
