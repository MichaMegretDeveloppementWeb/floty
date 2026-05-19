<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\RentalDiscount;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalDiscount>
 */
final class RentalDiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2026);
        $startMonth = fake()->numberBetween(1, 6);
        $endMonth = fake()->numberBetween(7, 12);

        return [
            'company_id' => Company::factory(),
            'start_date' => sprintf('%04d-%02d-01', $year, $startMonth),
            'end_date' => sprintf('%04d-%02d-28', $year, $endMonth),
            // 100..2000 bp = 1..20 % (realistic B2B range).
            'discount_basis_points' => fake()->numberBetween(100, 2000),
            'label' => fake()->optional(0.6)->randomElement([
                'Pack fidélité',
                'Remise volume',
                'Engagement annuel',
                'Offre lancement',
                'Négociation commerciale',
            ]),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by_user_id' => null,
        ];
    }

    /** Attach the discount to an existing company (skip the default factory creation). */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }

    /** Set the exact discount period. */
    public function withPeriod(CarbonInterface $start, CarbonInterface $end): static
    {
        return $this->state(fn (): array => [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    /** Set the percentage (UI input as %, stored as basis points: 10.5 -> 1050 bp). */
    public function withDiscountPercent(float $percent): static
    {
        return $this->state(fn (): array => [
            'discount_basis_points' => (int) round($percent * 100),
        ]);
    }

    /** Set the percentage as raw basis points (for precise test values). */
    public function withDiscountBasisPoints(int $basisPoints): static
    {
        return $this->state(fn (): array => [
            'discount_basis_points' => $basisPoints,
        ]);
    }

    /**
     * Attach the discount to a list of vehicles after creation. To target every
     * vehicle of the company, do not call this state: an empty pivot has the
     * "applies to all" semantics handled in the application.
     *
     * @param  list<Vehicle>  $vehicles
     */
    public function appliesToVehicles(array $vehicles): static
    {
        return $this->afterCreating(function (RentalDiscount $discount) use ($vehicles): void {
            $discount->vehicles()->attach(array_map(
                static fn (Vehicle $v): int => $v->id,
                $vehicles,
            ));
        });
    }
}
