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
