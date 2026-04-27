<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
final class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'company_id' => Company::factory(),
            'driver_id' => null,
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ];
    }
}
