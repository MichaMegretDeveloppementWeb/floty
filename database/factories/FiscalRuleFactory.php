<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FiscalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalRule>
 *
 * Factory de l'index `fiscal_rules` (Phase 13 D5.14 · ADR-0022 v1.4).
 * Seules les colonnes d'index sont peuplées · les anciennes colonnes
 * miroir (name, description, legal_basis, etc.) ont été droppées et
 * vivent désormais dans les classes PHP des règles.
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
