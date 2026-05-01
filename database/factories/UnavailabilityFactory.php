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
            'has_fiscal_impact' => $type->isFiscallyReductive(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * State « fourrière publique » — réducteur fiscal, choix par défaut
     * pour les ex-`pound` historiques (cf. ADR-0016 rev. 1.1).
     */
    public function poundPublic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UnavailabilityType::PoundPublic,
            'has_fiscal_impact' => true,
        ]);
    }

    /**
     * State « interdiction de circuler post-sinistre » — réducteur.
     */
    public function accidentNoCirculation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UnavailabilityType::AccidentNoCirculation,
            'has_fiscal_impact' => true,
        ]);
    }

    /**
     * State « suspension du certificat d'immatriculation » — réducteur.
     */
    public function ciSuspension(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UnavailabilityType::CiSuspension,
            'has_fiscal_impact' => true,
        ]);
    }

    /**
     * State « maintenance courante » — non réducteur, cas par défaut le
     * plus fréquent dans les tests.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'end_date' => null,
        ]);
    }
}
