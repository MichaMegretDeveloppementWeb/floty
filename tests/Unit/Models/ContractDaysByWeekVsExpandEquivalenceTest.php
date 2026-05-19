<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use App\Support\Date\IsoWeeks;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test d'équivalence stricte entre {@see Contract::daysByWeekInYear} et
 * `groupBy(format('W'))` sur `Contract::expandToDaysInYear`. Couvre les
 * bordures délicates ISO 8601 : années à 53 semaines, jours en bordure
 * d'année appartenant à la semaine ISO de l'année voisine, contrats
 * débordants.
 *
 * **Pourquoi** : `daysByWeekInYear` est la variante perf qui ne
 * matérialise pas l'array de 365 strings ; itère semaine par semaine.
 * Cette équivalence garantit que les 3 méthodes
 * `ContractQueryService::loadWeekDensity`, `loadWeekDensityForCompany`
 * et `loadVehicleWeeklyBreakdown` peuvent être migrées sans changer
 * la sortie côté UI heatmap planning + timeline 52w véhicule.
 *
 * Cf. plan optim perf 2026-05-15 S2.1.
 */
final class ContractDaysByWeekVsExpandEquivalenceTest extends TestCase
{
    /**
     * @return iterable<string, array{startDate: string, endDate: string, year: int}>
     */
    public static function casesProvider(): iterable
    {
        // Cas nominaux (mêmes fixtures que ContractCountVsExpandEquivalenceTest).
        yield 'cas nominal · contrat dans l\'année' => [
            'startDate' => '2026-03-15',
            'endDate' => '2026-09-30',
            'year' => 2026,
        ];

        yield 'contrat plein année (non bissextile)' => [
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
            'year' => 2026,
        ];

        yield 'contrat plein année bissextile' => [
            'startDate' => '2024-01-01',
            'endDate' => '2024-12-31',
            'year' => 2024,
        ];

        yield 'clipping gauche · contrat débordant à gauche' => [
            'startDate' => '2025-10-01',
            'endDate' => '2026-04-30',
            'year' => 2026,
        ];

        yield 'clipping droite · contrat débordant à droite' => [
            'startDate' => '2026-10-01',
            'endDate' => '2027-03-31',
            'year' => 2026,
        ];

        yield 'clipping double · contrat débordant des deux côtés' => [
            'startDate' => '2025-06-01',
            'endDate' => '2027-06-01',
            'year' => 2026,
        ];

        yield 'contrat hors année · entièrement avant' => [
            'startDate' => '2024-05-01',
            'endDate' => '2024-12-31',
            'year' => 2026,
        ];

        yield 'contrat hors année · entièrement après' => [
            'startDate' => '2027-05-01',
            'endDate' => '2027-12-31',
            'year' => 2026,
        ];

        yield 'contrat 1 jour' => [
            'startDate' => '2026-05-15',
            'endDate' => '2026-05-15',
            'year' => 2026,
        ];

        yield 'contrat collé à la borne 1er janvier' => [
            'startDate' => '2026-01-01',
            'endDate' => '2026-01-01',
            'year' => 2026,
        ];

        yield 'contrat collé à la borne 31 décembre' => [
            'startDate' => '2026-12-31',
            'endDate' => '2026-12-31',
            'year' => 2026,
        ];

        // Bordures ISO weeks : les cas où la sémantique « format(W) »
        // peut surprendre. ISO 8601 : une semaine appartient à l'année
        // qui contient son jeudi.

        // 2024-12-30 (lundi) appartient à W01 de 2025 selon format('W').
        // Pour year=2024, ce jour est exclu (rangeEnd=2024-12-31), mais
        // 2024-12-30 et 2024-12-31 (mardi) sont tous deux en W01.
        yield 'bordure · fin 2024 dans W01 (2025)' => [
            'startDate' => '2024-12-25',
            'endDate' => '2024-12-31',
            'year' => 2024,
        ];

        // 2026 a 53 semaines ISO (Jan 1 = jeudi → 53 semaines).
        // Dec 28-31 2026 (lundi-jeudi) = W53.
        yield 'bordure · 2026 a 53 semaines (W53 = dec 28-31)' => [
            'startDate' => '2026-12-25',
            'endDate' => '2026-12-31',
            'year' => 2026,
        ];

        // Contrat couvrant pile la transition année : clipping à droite
        // mais inclut la bordure W01 de 2025 (sur fin 2024).
        yield 'bordure · transition 2024→2025 clipped à 2024' => [
            'startDate' => '2024-12-28',
            'endDate' => '2025-01-05',
            'year' => 2024,
        ];

        // Symétrique : pour year=2025, jan 1-5 2025 sont en W01 mais
        // 2024-12-30/31 aussi (W01 selon ISO). Côté year=2025, on ne
        // garde que jan 1-5 → W01 = 5 jours.
        yield 'bordure · transition 2024→2025 clipped à 2025' => [
            'startDate' => '2024-12-28',
            'endDate' => '2025-01-05',
            'year' => 2025,
        ];

        // Année 2023 finit en W52 (dec 25-31 sont tous en W52).
        // 2024 commence W01 le lundi 1 jan 2024.
        yield 'année 2023 finit nettement W52' => [
            'startDate' => '2023-12-18',
            'endDate' => '2024-01-07',
            'year' => 2023,
        ];

        // Plage entière sur W53 d'une année 53 (2026).
        yield 'plage entière dans W53 (2026)' => [
            'startDate' => '2026-12-28',
            'endDate' => '2026-12-31',
            'year' => 2026,
        ];

        // Multi-semaines à cheval avec semaines pleines au milieu.
        yield 'multi-semaines pleines + bord' => [
            'startDate' => '2026-02-09',
            'endDate' => '2026-03-08',
            'year' => 2026,
        ];

        // Cas pathologique : 1 jour pile sur dimanche (fin de semaine ISO).
        yield 'contrat 1 jour dimanche' => [
            'startDate' => '2026-03-15',
            'endDate' => '2026-03-15',
            'year' => 2026,
        ];

        // Cas pathologique : 1 jour pile sur lundi (début de semaine ISO).
        yield 'contrat 1 jour lundi' => [
            'startDate' => '2026-03-16',
            'endDate' => '2026-03-16',
            'year' => 2026,
        ];
    }

    /**
     * Vérifie que `daysByWeekInYear` retourne EXACTEMENT le même
     * dictionnaire `cell → days` que `groupBy(cellIndex)` sur
     * `expandToDaysInYear`, sur tous les cas du provider.
     *
     * SC16 (2026-05-18) : changement de convention de l'oracle ·
     * indexation par CELLULE (1..53) dans la heatmap year au lieu du
     * numéro de semaine ISO `format('W')`. La cellule N correspond aux
     * jours de la semaine commençant au lundi N de la grille year ·
     * cellule 1 = semaine contenant le 1er janvier.
     */
    #[Test]
    #[DataProvider('casesProvider')]
    public function days_by_week_egal_groupby_expand_sur_tous_les_cas(
        string $startDate,
        string $endDate,
        int $year,
    ): void {
        $contract = $this->makeContract($startDate, $endDate);

        // Oracle : groupby par index de cellule sur expand.
        $origin = IsoWeeks::cellOriginForYear($year);
        $expected = [];
        foreach ($contract->expandToDaysInYear($year) as $iso) {
            $date = new DateTimeImmutable($iso);
            $originPhp = new DateTimeImmutable($origin->toDateString());
            $diffDays = (int) $originPhp->diff($date)->days;
            $cellIdx = (int) floor($diffDays / 7) + 1;
            $expected[$cellIdx] = ($expected[$cellIdx] ?? 0) + 1;
        }
        ksort($expected);

        // Méthode arithmétique pure.
        $actual = $contract->daysByWeekInYear($year);
        ksort($actual);

        $this->assertSame(
            $expected,
            $actual,
            "daysByWeekInYear({$year}) doit matcher groupBy(cellIndex) sur expandToDaysInYear pour [{$startDate} → {$endDate}].",
        );
    }

    /**
     * Garde-fou de cohérence : la somme des jours par semaine doit
     * égaler `countDaysInYear` (qui est lui-même équivalent à
     * `count(expandToDaysInYear)` cf. ContractCountVsExpandEquivalenceTest).
     */
    #[Test]
    #[DataProvider('casesProvider')]
    public function somme_des_jours_par_semaine_egale_count_days_in_year(
        string $startDate,
        string $endDate,
        int $year,
    ): void {
        $contract = $this->makeContract($startDate, $endDate);

        $byWeek = $contract->daysByWeekInYear($year);
        $sumByWeek = array_sum($byWeek);
        $countDays = $contract->countDaysInYear($year);

        $this->assertSame(
            $countDays,
            $sumByWeek,
            "Σ daysByWeekInYear doit égaler countDaysInYear pour [{$startDate} → {$endDate}] year={$year}.",
        );
    }

    private function makeContract(string $startDate, string $endDate): Contract
    {
        // Pas de DB : on instancie un Contract en mémoire avec juste les
        // dates castées comme Carbon. `forceFill` passe par le cast
        // Eloquent `'date'` configuré sur Contract → on récupère un
        // Carbon prêt à l'emploi pour `expandToDaysInYear` /
        // `daysByWeekInYear` qui font `->toImmutable()` dessus.
        return (new Contract)->forceFill([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
