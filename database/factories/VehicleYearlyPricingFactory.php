<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Daily/weekly/monthly pricing per vehicle for a given year.
 * Defaults: 90 EUR / 500 EUR / 1800 EUR (standard 5-door gasoline city car).
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

    /** Explicit cents rates (readability helper for tests). */
    public function withRates(int $dailyCents, int $weeklyCents, int $monthlyCents): self
    {
        return $this->state(fn (): array => [
            'daily_rate_cents' => $dailyCents,
            'weekly_rate_cents' => $weeklyCents,
            'monthly_rate_cents' => $monthlyCents,
        ]);
    }
}
