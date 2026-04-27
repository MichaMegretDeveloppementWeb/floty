<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Company\CompanyColor;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shortCode = strtoupper(fake()->unique()->bothify('???'));

        return [
            'legal_name' => fake()->company(),
            'siren' => fake()->numerify('#########'),
            'siret' => null,
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'FR',
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'short_code' => $shortCode,
            'color' => fake()->randomElement(CompanyColor::cases()),
            'is_active' => true,
            'deactivated_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
