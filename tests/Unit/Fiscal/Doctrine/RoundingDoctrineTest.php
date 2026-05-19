<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Doctrine;

use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use App\Services\Fiscal\FleetFiscalAggregator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Doctrine d'arrondi fiscal Floty V1 : « arrondir la somme des bruts
 * à la fin », pas « arrondir chaque ligne puis sommer ».
 *
 * `round(Σ raws) ≠ Σ(round(lignes))` peut différer d'un cent sur des
 * cas pathologiques `.005`. Trade-off accepté V1 : précision maximale
 * jusqu'au dernier moment, cohérence visuelle imparfaite sur extrêmes.
 *
 * Ce test cristallise la doctrine : s'il casse, refonte silencieuse à
 * confronter à un commit explicite avec validation BOFiP.
 */
final class RoundingDoctrineTest extends TestCase
{
    #[Test]
    public function doctrine_floty_v1_arrondit_la_somme_brute_pas_les_lignes(): void
    {
        // Cas `.005` : PHP_ROUND_HALF_UP arrondit vers le haut.
        $lignesRaw = [
            12.005,
            34.005,
            56.005,
        ];

        $sommeRaw = array_sum($lignesRaw);
        $totalDoctrineFloty = round($sommeRaw, 2, PHP_ROUND_HALF_UP);

        $totalDoctrineComptable = array_sum(array_map(
            static fn (float $r): float => round($r, 2, PHP_ROUND_HALF_UP),
            $lignesRaw,
        ));

        self::assertSame(102.02, $totalDoctrineFloty);
        self::assertSame(102.03, $totalDoctrineComptable);
        self::assertNotEquals($totalDoctrineFloty, $totalDoctrineComptable);
    }

    #[Test]
    public function doctrine_floty_v1_appliquee_par_declaration_fiscal_engine(): void
    {
        // Garde-fou statique : `DeclarationFiscalEngine::compute()` doit
        // calculer les totaux via `round($totalCo2Raw, ...)`, pas
        // `array_sum` des lignes arrondies.
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
        // Cohérence doctrine cross-services (Vehicle/Company Show, billing).
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
        // Sur la majorité des cas réels, les 2 doctrines concordent.
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

        // assertEqualsWithDelta : tolère micro-imprécision IEEE 754.
        self::assertEqualsWithDelta($totalFloty, $totalComptable, 0.001);
    }
}
