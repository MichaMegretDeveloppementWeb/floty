<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests purs (sans bootstrap Laravel) du contexte d'année fiscale.
 *
 * Couvre la logique bissextile (cas limite 1900/2000/2100/2400) et la
 * lecture des années disponibles via un `Repository` stub.
 *
 * Note chantier J (ADR-0020) : `FiscalYearResolver` (qui résolvait
 * l'année active via session) a été supprimé. La résolution de l'année
 * vit désormais par page via `?year=` URL.
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
    public function available_years_normalise_en_int_list(): void
    {
        $context = $this->makeContext(availableYears: ['2024', 2025, '2026']);

        $this->assertSame([2024, 2025, 2026], $context->availableYears());
    }

    #[Test]
    public function is_supported_verifie_la_liste(): void
    {
        $context = $this->makeContext(availableYears: [2024, 2025]);

        $this->assertTrue($context->isSupported(2024));
        $this->assertTrue($context->isSupported(2025));
        $this->assertFalse($context->isSupported(2026));
    }

    /**
     * @param  array<int, int|string>  $availableYears
     */
    private function makeContext(array $availableYears = [2024]): FiscalYearContext
    {
        $config = new Repository([
            'floty' => [
                'fiscal' => [
                    'available_years' => $availableYears,
                ],
            ],
        ]);

        return new FiscalYearContext($config);
    }
}
