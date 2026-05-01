<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Driver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
final class DriverFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ];
    }

    /**
     * Crée le driver puis l'attache à une company donnée avec joined_at.
     * Pratique pour les tests qui veulent un driver actif dans une company.
     */
    public function activeIn(Company $company, ?CarbonInterface $joinedAt = null): static
    {
        return $this->afterCreating(function (Driver $driver) use ($company, $joinedAt): void {
            $driver->companies()->attach($company->id, [
                'joined_at' => ($joinedAt ?? now()->subYear())->toDateString(),
                'left_at' => null,
            ]);
        });
    }

    /**
     * Driver sorti d'une company à la date donnée.
     */
    public function leftCompanyOn(
        Company $company,
        CarbonInterface $joinedAt,
        CarbonInterface $leftAt,
    ): static {
        return $this->afterCreating(function (Driver $driver) use ($company, $joinedAt, $leftAt): void {
            $driver->companies()->attach($company->id, [
                'joined_at' => $joinedAt->toDateString(),
                'left_at' => $leftAt->toDateString(),
            ]);
        });
    }
}
