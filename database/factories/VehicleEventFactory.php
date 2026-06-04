<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleEvent>
 */
final class VehicleEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(VehicleEventType::cases());

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

    /** Public pound: fiscally reducing. */
    public function poundPublic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => VehicleEventType::PoundPublic,
            'has_fiscal_impact' => true,
        ]);
    }

    /** No-circulation order after an accident: fiscally reducing. */
    public function accidentNoCirculation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => VehicleEventType::AccidentNoCirculation,
            'has_fiscal_impact' => true,
        ]);
    }

    /** Registration certificate suspension: fiscally reducing. */
    public function ciSuspension(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => VehicleEventType::CiSuspension,
            'has_fiscal_impact' => true,
        ]);
    }

    /** Routine maintenance: non-reducing (most common test default). */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => VehicleEventType::Maintenance,
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
