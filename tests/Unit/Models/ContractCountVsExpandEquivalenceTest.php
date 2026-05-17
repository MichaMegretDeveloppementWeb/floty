<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test d'équivalence stricte entre {@see Contract::countDaysInYear} et
 * `count(Contract::expandToDaysInYear)` sur tous les cas de bornes
 * possibles (clipping gauche, droite, des deux côtés, hors année,
 * contrat 1 jour, année bissextile).
 *
 * **Pourquoi** · `countDaysInYear` est la variante perf qui ne
 * matérialise pas l'array de 365 strings. Cette équivalence garantit
 * que les 11 sites de comptage simple (`count(expandToDaysInYear)`)
 * peuvent être migrés sans aucun risque sur les calculs fiscaux.
 *
 * Cf. plan-remédiation Vague 1 Lot 3 D02 (F-12-006 et consolidés ·
 * Option A pragmatique).
 */
final class ContractCountVsExpandEquivalenceTest extends TestCase
{
    /**
     * @return iterable<string, array{startDate: string, endDate: string, year: int, expectedDays: int}>
     */
    public static function casesProvider(): iterable
    {
        yield 'cas nominal · contrat dans l\'année' => [
            'startDate' => '2026-03-15',
            'endDate' => '2026-09-30',
            'year' => 2026,
            'expectedDays' => 200, // 17 (mars) + 30 + 31 + 30 + 31 + 31 + 30 = 200
        ];

        yield 'contrat plein année (non bissextile)' => [
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
            'year' => 2026,
            'expectedDays' => 365,
        ];

        yield 'contrat plein année bissextile' => [
            'startDate' => '2024-01-01',
            'endDate' => '2024-12-31',
            'year' => 2024,
            'expectedDays' => 366,
        ];

        yield 'clipping gauche · contrat débordant à gauche' => [
            'startDate' => '2025-10-01',
            'endDate' => '2026-04-30',
            'year' => 2026,
            'expectedDays' => 120, // 31+28+31+30 (jan-avr 2026)
        ];

        yield 'clipping droite · contrat débordant à droite' => [
            'startDate' => '2026-10-01',
            'endDate' => '2027-03-31',
            'year' => 2026,
            'expectedDays' => 92, // 31 (oct) + 30 (nov) + 31 (déc)
        ];

        yield 'clipping double · contrat débordant des deux côtés' => [
            'startDate' => '2025-06-01',
            'endDate' => '2027-06-01',
            'year' => 2026,
            'expectedDays' => 365,
        ];

        yield 'contrat hors année · entièrement avant' => [
            'startDate' => '2024-05-01',
            'endDate' => '2024-12-31',
            'year' => 2026,
            'expectedDays' => 0,
        ];

        yield 'contrat hors année · entièrement après' => [
            'startDate' => '2027-05-01',
            'endDate' => '2027-12-31',
            'year' => 2026,
            'expectedDays' => 0,
        ];

        yield 'contrat 1 jour' => [
            'startDate' => '2026-05-15',
            'endDate' => '2026-05-15',
            'year' => 2026,
            'expectedDays' => 1,
        ];

        yield 'contrat collé à la borne 1er janvier' => [
            'startDate' => '2026-01-01',
            'endDate' => '2026-01-01',
            'year' => 2026,
            'expectedDays' => 1,
        ];

        yield 'contrat collé à la borne 31 décembre' => [
            'startDate' => '2026-12-31',
            'endDate' => '2026-12-31',
            'year' => 2026,
            'expectedDays' => 1,
        ];
    }

    /**
     * Vérifie que les deux méthodes retournent EXACTEMENT le même nombre
     * de jours (équivalence stricte) sur tous les cas du provider.
     */
    #[Test]
    #[DataProvider('casesProvider')]
    public function count_egal_count_de_expand_sur_tous_les_cas(
        string $startDate,
        string $endDate,
        int $year,
        int $expectedDays,
    ): void {
        $contract = $this->makeContract($startDate, $endDate);

        $countResult = $contract->countDaysInYear($year);
        $expandResult = count($contract->expandToDaysInYear($year));

        $this->assertSame(
            $expectedDays,
            $countResult,
            "countDaysInYear({$year}) doit retourner {$expectedDays} pour [{$startDate} → {$endDate}].",
        );
        $this->assertSame(
            $expectedDays,
            $expandResult,
            "count(expandToDaysInYear({$year})) doit retourner {$expectedDays} pour [{$startDate} → {$endDate}].",
        );
        $this->assertSame(
            $expandResult,
            $countResult,
            'Les deux méthodes doivent retourner exactement la même valeur (équivalence stricte).',
        );
    }

    /**
     * @return iterable<string, array{startDate: string, endDate: string, year: int, upToDate: string, expectedDays: int}>
     */
    public static function upToCasesProvider(): iterable
    {
        // Cas pivot · upToDate >= 31/12 → équivalent à countDaysInYear.
        yield 'année passée, upToDate = 31/12' => [
            'startDate' => '2025-03-15',
            'endDate' => '2025-09-30',
            'year' => 2025,
            'upToDate' => '2025-12-31',
            'expectedDays' => 200,
        ];

        // upToDate au milieu de l'année courante → clip à upToDate.
        yield 'année courante YTD · upToDate clip droite' => [
            'startDate' => '2026-03-15',
            'endDate' => '2026-09-30',
            'year' => 2026,
            'upToDate' => '2026-05-31',
            'expectedDays' => 78, // 17 (mars 15→31) + 30 (avr) + 31 (mai)
        ];

        // upToDate avant le début du contrat → 0 jour.
        yield 'upToDate avant le début du contrat' => [
            'startDate' => '2026-06-01',
            'endDate' => '2026-12-31',
            'year' => 2026,
            'upToDate' => '2026-03-15',
            'expectedDays' => 0,
        ];

        // upToDate sur la borne start exactement → 1 jour.
        yield 'upToDate exactement = start_date' => [
            'startDate' => '2026-05-15',
            'endDate' => '2026-12-31',
            'year' => 2026,
            'upToDate' => '2026-05-15',
            'expectedDays' => 1,
        ];

        // Contrat débordant à gauche · upToDate clip droite.
        yield 'contrat débordant gauche · upToDate mi-année' => [
            'startDate' => '2025-11-01',
            'endDate' => '2026-08-15',
            'year' => 2026,
            'upToDate' => '2026-04-30',
            'expectedDays' => 120, // 31+28+31+30 (jan-avr 2026)
        ];

        // upToDate avant l'année · 0 jour.
        yield 'upToDate antérieur à year' => [
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
            'year' => 2026,
            'upToDate' => '2025-12-31',
            'expectedDays' => 0,
        ];

        // Contrat hors année · 0 jour quel que soit upToDate.
        yield 'contrat hors année · upToDate dans year' => [
            'startDate' => '2024-05-01',
            'endDate' => '2024-12-31',
            'year' => 2026,
            'upToDate' => '2026-06-15',
            'expectedDays' => 0,
        ];
    }

    /**
     * Équivalence stricte entre {@see Contract::countDaysInYearUpTo} et
     * `count(array_filter(expandToDaysInYear, fn($d) => $d <= upToDate))`.
     * Chantier perf Dashboard 2026-05-17 · garantit que la refacto de
     * `DashboardStatsService::computePeriodMetrics` (arithmétique au lieu
     * de matérialisation 365 strings) ne casse aucun calcul YTD.
     */
    #[Test]
    #[DataProvider('upToCasesProvider')]
    public function count_up_to_egal_count_filtre_de_expand(
        string $startDate,
        string $endDate,
        int $year,
        string $upToDate,
        int $expectedDays,
    ): void {
        $contract = $this->makeContract($startDate, $endDate);

        $countUpTo = $contract->countDaysInYearUpTo($year, $upToDate);
        $expandFiltered = count(array_filter(
            $contract->expandToDaysInYear($year),
            static fn (string $d): bool => $d <= $upToDate,
        ));

        $this->assertSame(
            $expectedDays,
            $countUpTo,
            "countDaysInYearUpTo({$year}, {$upToDate}) doit retourner {$expectedDays} pour [{$startDate} → {$endDate}].",
        );
        $this->assertSame(
            $expectedDays,
            $expandFiltered,
            "count(filter expandToDaysInYear) doit retourner {$expectedDays} pour [{$startDate} → {$endDate}], upTo={$upToDate}.",
        );
        $this->assertSame(
            $expandFiltered,
            $countUpTo,
            'Équivalence stricte arithmétique vs filtre exhaustif.',
        );
    }

    private function makeContract(string $startDate, string $endDate): Contract
    {
        // Pas de DB · on instancie un Contract en mémoire avec juste les
        // dates castées comme Carbon. `forceFill` passe par le cast
        // Eloquent `'date'` configuré sur Contract → on récupère un
        // Carbon prêt à l'emploi pour `expandToDaysInYear` /
        // `countDaysInYear` qui font `->toImmutable()` dessus.
        return (new Contract)->forceFill([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
