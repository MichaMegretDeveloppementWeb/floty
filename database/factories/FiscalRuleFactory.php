<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FiscalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Index factory for fiscal_rules. Only the index columns are populated; rule
 * metadata lives in the PHP rule classes.
 *
 * @extends Factory<FiscalRule>
 */
final class FiscalRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) (config('floty.fiscal.available_years', [2024])[0] ?? 2024);

        return [
            'rule_code' => fake()->unique()->regexify('R-[0-9]{4}-[0-9]{3}'),
            'fiscal_year' => $year,
            'code_reference' => 'app/Fiscal/Fake/FakeRule.php',
        ];
    }
}
