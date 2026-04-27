<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
final class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $acquisition = fake()->dateTimeBetween('-3 years', '-6 months');

        return [
            'license_plate' => strtoupper(fake()->unique()->bothify('??-###-??')),
            'brand' => fake()->randomElement(['Renault', 'Peugeot', 'Citroën', 'Volkswagen', 'Toyota']),
            'model' => fake()->randomElement(['Clio', '208', 'C3', 'Polo', 'Yaris', 'Megane', '308']),
            'vin' => strtoupper(fake()->unique()->regexify('[A-HJ-NPR-Z0-9]{17}')),
            'color' => fake()->safeColorName(),
            'photo_path' => null,
            'first_french_registration_date' => $acquisition,
            'first_origin_registration_date' => $acquisition,
            'first_economic_use_date' => $acquisition,
            'acquisition_date' => $acquisition,
            'exit_date' => null,
            'exit_reason' => null,
            'current_status' => VehicleStatus::Active,
            'mileage_current' => fake()->numberBetween(5_000, 200_000),
            'notes' => null,
        ];
    }
}
