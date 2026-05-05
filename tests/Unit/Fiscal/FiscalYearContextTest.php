<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests purs (sans bootstrap Laravel) du contexte d'année fiscale.
 *
 * Couvre la logique bissextile (cas limite 1900/2000/2100/2400) et la
 * validation d'année supportée déléguée au {@see FiscalRuleRegistry}.
 *
 * Note chantier η Phase 5 : la config statique
 * `floty.fiscal.available_years` a été supprimée. La source d'autorité
 * pour `isSupported()` est désormais le registry des règles codées
 * (sémantique : "le moteur fiscal sait calculer cette année").
 */
final class FiscalYearContextTest extends TestCase
{
    /**
     * @return list<array{int, int}>
     */
    public static function leapYearProvider(): array
    {
        return [
            [2000, 366], // div 400 → bissextile
            [1900, 365], // div 100 mais pas 400 → non bissextile
            [2024, 366],
            [2025, 365],
            [2028, 366],
            [2100, 365],
            [2400, 366],
        ];
    }

    #[DataProvider('leapYearProvider')]
    public function test_days_in_year_calcule_les_bissextiles(
        int $year,
        int $expected,
    ): void {
        $context = $this->makeContext();

        $this->assertSame($expected, $context->daysInYear($year));
    }

    #[Test]
    public function is_supported_delegue_au_registry(): void
    {
        $context = $this->makeContext(registeredYears: [2024, 2025]);

        $this->assertTrue($context->isSupported(2024));
        $this->assertTrue($context->isSupported(2025));
        $this->assertFalse($context->isSupported(2026));
    }

    /**
     * @param  list<int>  $registeredYears
     */
    private function makeContext(array $registeredYears = [2024]): FiscalYearContext
    {
        $registry = new FiscalRuleRegistry(new Container);

        // `registeredYears()` ne fait que renvoyer les clés de `$byYear`.
        // Une liste vide de classes suffit à enregistrer l'année dans le
        // registry pour les besoins du test.
        foreach ($registeredYears as $year) {
            $registry->register($year, []);
        }

        return new FiscalYearContext($registry);
    }
}
