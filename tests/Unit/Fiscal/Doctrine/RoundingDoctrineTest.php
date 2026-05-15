<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Doctrine;

use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use App\Services\Fiscal\FleetFiscalAggregator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Lot 5 D14 (F-19D2-010) · documente formellement la **doctrine
 * d'arrondi fiscal Floty V1** · « arrondir la somme des bruts à la
 * fin », et NON « arrondir chaque ligne puis sommer ».
 *
 * Conséquence visible · `round(Σ raws) ≠ Σ(round(lignes))` peut
 * différer d'un cent sur des cas pathologiques avec décimales `.005`.
 * Ce différentiel est **intentionnel** dans le V1 actuel · garde la
 * précision mathématique maximale jusqu'au dernier moment, conforme à
 * la pratique BOFiP/CGI sur l'agrégation de plusieurs taxes.
 *
 * **Inconvénient acté** · le PDF/UI peut afficher des sommes de lignes
 * qui ne correspondent pas exactement au total affiché (écart de l'ordre
 * du cent). Trade-off accepté pour V1 · pas de bug, pas de perte
 * fiscale, juste une cohérence visuelle imparfaite sur cas extrêmes.
 *
 * **Décision potentielle V2** · si un comptable client signale un
 * écart visible gênant pour audit, basculer vers la doctrine
 * « arrondir chaque ligne, sommer ensuite ». Ce changement nécessitera
 * une refonte coordonnée de `DeclarationFiscalEngine` lignes 250-309
 * + `FleetFiscalAggregator` consommateurs multiples + adaptation des
 * ~50 tests fiscaux qui assertent les totaux actuels. Hors-scope V1.
 *
 * **Garde-fou** · ce test cristallise la doctrine actuelle. S'il
 * casse un jour, c'est qu'une refonte silencieuse de l'arrondi a eu
 * lieu · à confronter à un commit explicite avec validation BOFiP.
 */
final class RoundingDoctrineTest extends TestCase
{
    #[Test]
    public function doctrine_floty_v1_arrondit_la_somme_brute_pas_les_lignes(): void
    {
        // Cas pathologique avec décimales `.005` qui font diverger les
        // 2 doctrines · `PHP_ROUND_HALF_UP` arrondit `.005` vers le
        // haut (`0.005 → 0.01`).
        $lignesRaw = [
            12.005,  // round → 12.01
            34.005,  // round → 34.01
            56.005,  // round → 56.01
        ];

        $sommeRaw = array_sum($lignesRaw);                              // 102.015
        $totalDoctrineFloty = round($sommeRaw, 2, PHP_ROUND_HALF_UP);   // 102.02 (arrondi à la fin)

        $totalDoctrineComptable = array_sum(array_map(
            static fn (float $r): float => round($r, 2, PHP_ROUND_HALF_UP),
            $lignesRaw,
        ));                                                              // 102.03 (Σ lignes arrondies)

        // Les 2 doctrines divergent d'un cent sur ce cas pathologique.
        self::assertSame(102.02, $totalDoctrineFloty);
        self::assertSame(102.03, $totalDoctrineComptable);
        self::assertNotEquals($totalDoctrineFloty, $totalDoctrineComptable);
    }

    #[Test]
    public function doctrine_floty_v1_appliquee_par_declaration_fiscal_engine(): void
    {
        // Garde-fou statique · vérifie par reflection que le code
        // de `DeclarationFiscalEngine::compute()` continue de calculer
        // les totaux annuels via `round($totalCo2Raw, ...)` (pas via
        // `array_sum` des lignes arrondies). Si ce pattern disparaît,
        // c'est une dérive doctrinale silencieuse à valider explicitement.
        $reflection = new ReflectionClass(DeclarationFiscalEngine::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertNotFalse($source, 'Le source de DeclarationFiscalEngine doit être lisible.');
        self::assertStringContainsString(
            'co2DueTotal: round($totalCo2Raw',
            $source,
            'La doctrine V1 « arrondir la somme brute » doit être préservée. Si ce pattern a changé, vérifier que c\'est une décision explicite avec validation BOFiP.',
        );
        self::assertStringContainsString(
            'pollutantsDueTotal: round($totalPollutantsRaw',
            $source,
            'La doctrine V1 « arrondir la somme brute » doit être préservée pour la taxe polluants aussi.',
        );
    }

    #[Test]
    public function doctrine_floty_v1_appliquee_par_fleet_fiscal_aggregator(): void
    {
        // Même garde-fou pour `FleetFiscalAggregator` · service consommé
        // par d'autres workflows fiscaux (Vehicle Show, Company Show,
        // billing). Cohérence doctrine sur tout le moteur fiscal.
        $reflection = new ReflectionClass(FleetFiscalAggregator::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertNotFalse($source, 'Le source de FleetFiscalAggregator doit être lisible.');
        self::assertStringContainsString(
            'round($totalRaw, 2, PHP_ROUND_HALF_UP)',
            $source,
            'La doctrine V1 « arrondir la somme brute » doit être préservée dans FleetFiscalAggregator (cohérence cross-services).',
        );
    }

    #[Test]
    public function cas_classiques_sans_decimales_pathologiques_les_2_doctrines_concordent(): void
    {
        // Sur la majorité des cas réels (montants en centimes entiers,
        // résultats de prorata simples), les 2 doctrines donnent le
        // même résultat. Le différentiel est strictement borné aux cas
        // `.005` ou similaires.
        $lignesNormales = [
            123.45,
            678.90,
            999.99,
        ];

        $sommeRaw = array_sum($lignesNormales);
        $totalFloty = round($sommeRaw, 2, PHP_ROUND_HALF_UP);
        $totalComptable = array_sum(array_map(
            static fn (float $r): float => round($r, 2, PHP_ROUND_HALF_UP),
            $lignesNormales,
        ));

        // assertEqualsWithDelta · les flottants peuvent avoir une
        // micro-imprécision IEEE 754 (1802.34 vs 1802.3400000000001),
        // négligeable au cent près mais qui casse `assertSame` strict.
        self::assertEqualsWithDelta($totalFloty, $totalComptable, 0.001);
    }
}
