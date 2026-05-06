<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tarifs jour/semaine/mois d'un véhicule pour une année donnée.
 *
 * Defaults raisonnables pour le seeder démo (citadine 5-portes essence
 * standard) :
 *   - 90 € / jour
 *   - 500 € / semaine
 *   - 1 800 € / mois
 *
 * State chaînable {@see self::forYear()} pour fixer une année spécifique.
 *
 * @extends Factory<VehicleYearlyPricing>
 */
final class VehicleYearlyPricingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'year' => Carbon::now()->year,
            'daily_rate_cents' => 9_000,
            'weekly_rate_cents' => 50_000,
            'monthly_rate_cents' => 180_000,
        ];
    }

    public function forYear(int $year): self
    {
        return $this->state(fn (): array => ['year' => $year]);
    }

    /**
     * Tarifs explicites en cents (helper de lisibilité dans les tests).
     */
    public function withRates(int $dailyCents, int $weeklyCents, int $monthlyCents): self
    {
        return $this->state(fn (): array => [
            'daily_rate_cents' => $dailyCents,
            'weekly_rate_cents' => $weeklyCents,
            'monthly_rate_cents' => $monthlyCents,
        ]);
    }
}
