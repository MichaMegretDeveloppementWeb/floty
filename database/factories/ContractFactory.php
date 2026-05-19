<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
final class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '+1 month');
        $duration = fake()->numberBetween(1, 90);
        $end = (clone $start)->modify("+{$duration} days");

        return [
            'vehicle_id' => Vehicle::factory(),
            'company_id' => Company::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'contract_reference' => null,
            'contract_type' => fake()->randomElement([ContractType::Lcd, ContractType::Lld]),
            'notes' => null,
        ];
    }

    /** Short-term contract (LCD): up to 30 days. */
    public function lcd(): static
    {
        return $this->state(function (array $attributes): array {
            $start = fake()->dateTimeBetween('-3 months', 'now');
            $duration = fake()->numberBetween(1, 30);
            $end = (clone $start)->modify("+{$duration} days");

            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'contract_type' => ContractType::Lcd,
            ];
        });
    }

    /** Long-term contract (LLD): always above 31 days. */
    public function lld(): static
    {
        return $this->state(function (array $attributes): array {
            $start = fake()->dateTimeBetween('-6 months', '-1 month');
            $duration = fake()->numberBetween(60, 365);
            $end = (clone $start)->modify("+{$duration} days");

            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'contract_type' => ContractType::Lld,
            ];
        });
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (): array => ['vehicle_id' => $vehicle->id]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }

    /**
     * Attach N drivers to the contract via the contract_drivers pivot.
     *
     * @param  list<Driver>  $drivers
     */
    public function withDrivers(array $drivers): static
    {
        return $this->afterCreating(function (Contract $contract) use ($drivers): void {
            $contract->drivers()->attach(
                array_map(static fn (Driver $d): int => $d->id, $drivers),
            );
        });
    }

    /** Force both start and end dates inside the given year. */
    public function inYear(int $year): static
    {
        return $this->state(function (array $attributes) use ($year): array {
            $startMonth = fake()->numberBetween(1, 11);
            $startDay = fake()->numberBetween(1, 20);
            $start = sprintf('%04d-%02d-%02d', $year, $startMonth, $startDay);

            $duration = fake()->numberBetween(5, 30);
            $end = (new \DateTimeImmutable($start))->modify("+{$duration} days");

            // Clamp end_date to the same year.
            if ((int) $end->format('Y') !== $year) {
                $end = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
            }

            return [
                'start_date' => $start,
                'end_date' => $end->format('Y-m-d'),
            ];
        });
    }
}
