<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Fiscal\Year2024\Year2024Boot;
use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garantit qu'aucune règle Year2024 ne se retrouve sans contenu pédagogique
 * et que l'index BDD contient exactement les codes des classes PHP.
 */
final class FiscalRulesContentCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chaque_regle_php_a_un_pedagogical_content_complet(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        $boot = app(Year2024Boot::class);
        $allClasses = array_merge($boot->rules(), $boot->informativeRules());

        self::assertCount(32, $allClasses);

        $codesInDb = FiscalRule::query()
            ->where('fiscal_year', 2024)
            ->pluck('rule_code')
            ->all();
        self::assertCount(32, $codesInDb);

        foreach ($allClasses as $class) {
            $rule = app($class);
            $code = $rule->ruleCode();

            self::assertContains(
                $code,
                $codesInDb,
                sprintf('%s · classe PHP existante mais absente de l\'index BDD.', $code),
            );

            $content = $rule->pedagogicalContent();

            foreach (['title', 'pitch'] as $field) {
                self::assertNotEmpty(
                    $content->{$field},
                    sprintf('%s · champ %s vide dans pedagogicalContent().', $code, $field),
                );
            }
        }
    }
}
