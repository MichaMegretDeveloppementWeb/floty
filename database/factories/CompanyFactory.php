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
            // Forcés à false : R-2024-018 (OIG) et R-2024-019 (EIRL)
            // sont des stubs en V1 (cf. note Fillable Company.php).
            // Les tests qui veulent une entreprise OIG/EIRL doivent
            // explicitement override ces flags via state — on ne veut pas
            // qu'une factory random produise un cas non couvert.
            'is_oig' => false,
            'is_individual_business' => false,
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
