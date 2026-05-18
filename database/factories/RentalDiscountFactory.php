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
            // 1..2500 bp = 0,01..25,00 % · plage réaliste B2B
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

    /**
     * Attache la réduction à une company existante (évite la création
     * d'une nouvelle Company par la définition par défaut).
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }

    /**
     * Fixe la période exacte de la réduction.
     */
    public function withPeriod(CarbonInterface $start, CarbonInterface $end): static
    {
        return $this->state(fn (): array => [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    /**
     * Fixe le pourcentage (entrée en `%` UI, conversion bp interne).
     * `withDiscountPercent(10.5)` -> 1050 bp.
     */
    public function withDiscountPercent(float $percent): static
    {
        return $this->state(fn (): array => [
            'discount_basis_points' => (int) round($percent * 100),
        ]);
    }

    /**
     * Fixe le pourcentage en basis points (pour tests précis).
     */
    public function withDiscountBasisPoints(int $basisPoints): static
    {
        return $this->state(fn (): array => [
            'discount_basis_points' => $basisPoints,
        ]);
    }

    /**
     * Attache la réduction à une liste de véhicules après création.
     * **Liste vide volontairement non gérée ici** · pour signifier
     * « applique à tous », ne pas appeler ce state · le pivot reste vide
     * et la sémantique applicative s'en charge.
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
