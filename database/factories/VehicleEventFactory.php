<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleEvent>
 *
 * Default: a non-reductive named event. The reductive states (poundPublic,
 * accidentNoCirculation, ciSuspension) mirror the frozen reductive natures of
 * the catalogue by setting the denormalised `has_fiscal_impact` directly (the
 * fiscal rules read only that boolean); attach the matching nature rows with
 * {@see self::withCategories()} when a test needs them.
 */
final class VehicleEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');
        $end = (clone $start)->modify('+'.fake()->numberBetween(1, 14).' days');

        return [
            'vehicle_id' => Vehicle::factory(),
            'title' => fake()->words(3, true),
            'has_fiscal_impact' => false,
            'implies_unavailability' => true,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /** Public pound: fiscally reducing. */
    public function poundPublic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Mise en fourrière',
            'has_fiscal_impact' => true,
            'implies_unavailability' => true,
        ]);
    }

    /** No-circulation order after an accident: fiscally reducing. */
    public function accidentNoCirculation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Interdiction de circuler',
            'has_fiscal_impact' => true,
            'implies_unavailability' => true,
        ]);
    }

    /** Registration certificate suspension: fiscally reducing. */
    public function ciSuspension(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Suspension du CI',
            'has_fiscal_impact' => true,
            'implies_unavailability' => true,
        ]);
    }

    /** Routine maintenance: non-reducing (most common test default). */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Entretien courant',
            'has_fiscal_impact' => false,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'end_date' => null,
        ]);
    }

    /**
     * Named user event, never fiscally reductive, with an opt-in
     * unavailability flag. Natures live in a child table · attach them with
     * {@see self::withCategories()}.
     */
    public function custom(
        string $title = 'Événement personnalisé',
        bool $impliesUnavailability = true,
    ): static {
        return $this->state(fn (array $attributes): array => [
            'title' => $title,
            'has_fiscal_impact' => false,
            'implies_unavailability' => $impliesUnavailability,
        ]);
    }

    /**
     * Attach natures (child rows) after creation.
     */
    public function withCategories(string ...$categories): static
    {
        return $this->afterCreating(function (VehicleEvent $event) use ($categories): void {
            foreach ($categories as $category) {
                $event->categories()->create(['category' => $category]);
            }
        });
    }
}
