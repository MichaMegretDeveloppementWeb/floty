<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Fiscal\ValueObjects\ProgressiveScale;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Non-régression sur les barèmes 2024 (R-2024-010/011/012/014).
 *
 * Les valeurs attendues sont issues de `taxes-rules/2024.md` — sections
 * « Tests unitaires de référence » et exemples BOFiP officiels.
 *
 * Depuis la phase 1.8, les barèmes vivent à l'intérieur des classes
 * règles `R2024_010_*`, `R2024_011_*`, `R2024_012_*`. On les extrait
 * via reflection pour tester directement le `ProgressiveScale`
 * encapsulé — c'est délibéré : ce test est un **filet de sécurité**
 * qui valide les valeurs publiées DGFiP, pas l'API publique.
 */
final class BracketsCatalog2024Test extends TestCase
{
    /**
     * @return list<array{string, int, float}>
     */
    public static function wltpCases(): array
    {
        return [
            ['wltp', 0, 0.0],
            ['wltp', 14, 0.0],
            ['wltp', 15, 1.0],
            ['wltp', 55, 41.0],
            ['wltp', 56, 43.0],
            ['wltp', 63, 57.0],
            ['wltp', 100, 173.0],
            ['wltp', 130, 383.0],
            ['wltp', 155, 1433.0],
            ['wltp', 175, 2633.0],
            ['wltp', 176, 2698.0],
            ['wltp', 200, 4258.0],
            ['nedc', 130, 1282.0],
            ['pa', 1, 1500.0],
            ['pa', 3, 4500.0],
            ['pa', 4, 6750.0],
            ['pa', 7, 15000.0],
            ['pa', 10, 26250.0],
            ['pa', 11, 31000.0],
            ['pa', 15, 50000.0],
            ['pa', 16, 56000.0],
        ];
    }

    #[DataProvider('wltpCases')]
    public function test_progressive_bracket_matches_dgfip_reference(
        string $scale,
        int $value,
        float $expected,
    ): void {
        $rule = match ($scale) {
            'wltp' => new R2024_010_WltpProgressive,
            'nedc' => new R2024_011_NedcProgressive,
            'pa' => new R2024_012_PaProgressive,
        };

        $progressive = (new ReflectionClass($rule))->getProperty('scale')->getValue($rule);
        self::assertInstanceOf(ProgressiveScale::class, $progressive);
        $actual = $progressive->apply($value);

        self::assertSame(
            $expected,
            $actual,
            "Attendu {$expected} pour {$scale}({$value}), obtenu {$actual}",
        );
    }
}
