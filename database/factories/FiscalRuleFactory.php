<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Fiscal\RuleType;
use App\Models\FiscalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalRule>
 */
final class FiscalRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) config('floty.fiscal.current_year');

        return [
            'rule_code' => fake()->unique()->regexify('R-[0-9]{4}-[0-9]{3}'),
            'name' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'fiscal_year' => $year,
            'rule_type' => fake()->randomElement(RuleType::cases()),
            'taxes_concerned' => ['co2'],
            'applicability_start' => now()->subYears(2),
            'applicability_end' => null,
            'vehicle_characteristics_consumed' => null,
            'vehicle_characteristics_produced' => null,
            'legal_basis' => [['type' => 'CIBS', 'article' => 'L. 421-99']],
            'code_reference' => 'App\\Services\\Fiscal\\FiscalCalculator::calculate',
            'display_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
