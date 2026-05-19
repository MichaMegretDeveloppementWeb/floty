<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Fiscal\Year2025\Pricing\R2025_010_WltpProgressive;
use App\Fiscal\Year2025\Pricing\R2025_011_NedcProgressive;
use App\Fiscal\Year2025\Pricing\R2025_012_PaProgressive;
use App\Fiscal\Year2025\Pricing\R2025_014_PollutantsFlat;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Suite exhaustive des goldens BOFiP 2025.
 *
 * Complète `R2025_PricingScalesTest` et `R2025_023_E85AbatementTest`
 * avec ~80 cas calculés à la main, couvrant toutes les bordures
 * de tranches + transitions + cas extrêmes + combinaisons E85.
 *
 * Référence légale :
 * - WLTP L. 421-120 v 01/01/2025
 * - NEDC L. 421-121 v 01/01/2025
 * - PA L. 421-122 v 01/01/2025
 * - Polluants L. 421-135
 * - E85 L. 421-125 v 01/01/2025
 * - BOFiP BOI-AIS-MOB-10-30-20-20250528 § III-A exemple 1 (WLTP 100 g = 193 €)
 */
final class R2025_ExhaustiveGoldensTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: float, 2: string}>
     */
    public static function wltpGoldens(): array
    {
        return [
            // Tranche 0-9 : marginal 0 €
            'wltp_0_zero_strict' => [0, 0.0, '0 · pas d\'émissions'],
            'wltp_5_dans_tranche_0' => [5, 0.0, '5 g · dans tranche 0-9'],
            'wltp_9_borne_haute_tranche_0' => [9, 0.0, '9 g · borne haute tranche 0 (lowerExclusive 9)'],

            // Tranche 9-50 : marginal 1 €
            'wltp_10_borne_basse_tranche_1' => [10, 1.0, '(10-9)*1 = 1 €'],
            'wltp_30_milieu_tranche_1' => [30, 21.0, '(30-9)*1 = 21 €'],
            'wltp_50_borne_haute_tranche_1' => [50, 41.0, '(50-9)*1 = 41 €'],

            // Tranche 50-58 : marginal 2 €
            'wltp_51_borne_basse_tranche_2' => [51, 43.0, '41 + (51-50)*2 = 43 €'],
            'wltp_55_milieu_tranche_2' => [55, 51.0, '41 + (55-50)*2 = 51 €'],
            'wltp_58_borne_haute_tranche_2' => [58, 57.0, '41 + 16 = 57 €'],

            // Tranche 58-90 : marginal 3 €
            'wltp_60_borne_basse_tranche_3' => [60, 63.0, '57 + (60-58)*3 = 63 €'],
            'wltp_75_milieu_tranche_3' => [75, 108.0, '57 + (75-58)*3 = 108 €'],
            'wltp_90_borne_haute_tranche_3' => [90, 153.0, '57 + 96 = 153 €'],

            // Tranche 90-110 : marginal 4 €
            'wltp_100_exemple_bofip_193' => [100, 193.0, 'EXEMPLE BOFiP §240 · 41+16+96+40 = 193 €'],
            'wltp_110_borne_haute_tranche_4' => [110, 233.0, '153 + 80 = 233 €'],

            // Tranche 110-130 : marginal 10 €
            'wltp_120_milieu_tranche_5' => [120, 333.0, '233 + (120-110)*10 = 333 €'],
            'wltp_130_borne_haute_tranche_5' => [130, 433.0, '233 + 200 = 433 €'],

            // Tranche 130-150 : marginal 50 €
            'wltp_140_milieu_tranche_6' => [140, 933.0, '433 + (140-130)*50 = 933 €'],
            'wltp_150_borne_haute_tranche_6' => [150, 1433.0, '433 + 1000 = 1433 €'],

            // Tranche 150-170 : marginal 60 €
            'wltp_160_milieu_tranche_7' => [160, 2033.0, '1433 + (160-150)*60 = 2033 €'],
            'wltp_170_borne_haute_tranche_7' => [170, 2633.0, '1433 + 1200 = 2633 €'],

            // Tranche ouverte 170+ : marginal 65 €
            'wltp_175_dans_tranche_ouverte' => [175, 2958.0, '2633 + (175-170)*65 = 2958 €'],
            'wltp_250_limite_e85' => [250, 7833.0, '2633 + (250-170)*65 = 7833 €'],
            'wltp_400_extreme' => [400, 17583.0, '2633 + (400-170)*65 = 17583 €'],
        ];
    }

    #[Test]
    #[DataProvider('wltpGoldens')]
    public function wltp_golden_au_centime(int $co2, float $expected, string $explanation): void
    {
        $actual = $this->wltpTariff($co2);
        self::assertSame(
            $expected,
            $actual,
            "Cas '$explanation' · attendu $expected €, obtenu $actual €."
        );
    }

    // ============================================================
    // NEDC : 18 cas exhaustifs
    // ============================================================

    /**
     * @return array<string, array{0: int, 1: float, 2: string}>
     */
    public static function nedcGoldens(): array
    {
        return [
            'nedc_0' => [0, 0.0, '0 g · tranche 0'],
            'nedc_7_borne_haute_tranche_0' => [7, 0.0, '7 g · borne haute tranche 0'],

            'nedc_8_borne_basse_tranche_1' => [8, 1.0, '(8-7)*1 = 1 €'],
            'nedc_41_borne_haute_tranche_1' => [41, 34.0, '(41-7)*1 = 34 €'],

            'nedc_48_borne_haute_tranche_2' => [48, 48.0, '34 + (48-41)*2 = 48 €'],

            'nedc_74_borne_haute_tranche_3' => [74, 126.0, '48 + (74-48)*3 = 126 €'],

            'nedc_91_borne_haute_tranche_4' => [91, 194.0, '126 + (91-74)*4 = 194 €'],

            'nedc_100_typical' => [100, 284.0, '194 + (100-91)*10 = 284 €'],
            'nedc_107_borne_haute_tranche_5' => [107, 354.0, '194 + 160 = 354 €'],

            'nedc_115_milieu_tranche_6' => [115, 754.0, '354 + (115-107)*50 = 754 €'],
            'nedc_124_borne_haute_tranche_6' => [124, 1204.0, '354 + 850 = 1204 €'],

            'nedc_130_milieu_tranche_7' => [130, 1564.0, '1204 + (130-124)*60 = 1564 €'],
            'nedc_140_borne_haute_tranche_7' => [140, 2164.0, '1204 + 960 = 2164 €'],

            'nedc_145_dans_tranche_ouverte' => [145, 2489.0, '2164 + (145-140)*65 = 2489 €'],
            'nedc_200_extreme_borne_e85' => [200, 6064.0, '2164 + (200-140)*65 = 6064 €'],
            'nedc_250_limite_e85' => [250, 9314.0, '2164 + (250-140)*65 = 9314 €'],
        ];
    }

    #[Test]
    #[DataProvider('nedcGoldens')]
    public function nedc_golden_au_centime(int $co2, float $expected, string $explanation): void
    {
        $actual = $this->nedcTariff($co2);
        self::assertSame(
            $expected,
            $actual,
            "Cas '$explanation' · attendu $expected €, obtenu $actual €."
        );
    }

    // ============================================================
    // PA : 14 cas exhaustifs
    // ============================================================

    /**
     * @return array<string, array{0: int, 1: float, 2: string}>
     */
    public static function paGoldens(): array
    {
        return [
            // Tranche 0-3 : marginal 1750 €/CV
            'pa_1' => [1, 1750.0, '1 CV · 1*1750 = 1750 €'],
            'pa_3' => [3, 5250.0, '3 CV · 3*1750 = 5250 €'],

            // Tranche 3-6 : marginal 2500 €/CV
            'pa_4' => [4, 7750.0, '5250 + 2500 = 7750 €'],
            'pa_6' => [6, 12750.0, '5250 + 3*2500 = 12750 €'],

            // Tranche 6-10 : marginal 4250 €/CV
            'pa_7' => [7, 17000.0, '12750 + 4250 = 17000 €'],
            'pa_8_limite_e85' => [8, 21250.0, '12750 + 2*4250 = 21250 €'],
            'pa_10' => [10, 29750.0, '12750 + 4*4250 = 29750 €'],

            // Tranche 10-15 : marginal 5000 €/CV
            'pa_11' => [11, 34750.0, '29750 + 5000 = 34750 €'],
            'pa_12_borne_e85' => [12, 39750.0, '29750 + 2*5000 = 39750 €'],
            'pa_13_au_dessus_e85' => [13, 44750.0, '29750 + 3*5000 = 44750 €'],
            'pa_15' => [15, 54750.0, '29750 + 5*5000 = 54750 €'],

            // Tranche ouverte 15+ : marginal 6250 €/CV
            'pa_16' => [16, 61000.0, '54750 + 6250 = 61000 €'],
            'pa_20' => [20, 86000.0, '54750 + 5*6250 = 86000 €'],
            'pa_30_extreme' => [30, 148500.0, '54750 + 15*6250 = 148500 €'],
        ];
    }

    #[Test]
    #[DataProvider('paGoldens')]
    public function pa_golden_au_centime(int $cv, float $expected, string $explanation): void
    {
        $actual = $this->paTariff($cv);
        self::assertSame(
            $expected,
            $actual,
            "Cas '$explanation' · attendu $expected €, obtenu $actual €."
        );
    }

    // ============================================================
    // Polluants flat : 3 cas (toutes les catégories)
    // ============================================================

    #[Test]
    public function polluants_e_strict_zero(): void
    {
        self::assertSame(0.0, $this->pollutantsTariff(PollutantCategory::E));
    }

    #[Test]
    public function polluants_categorie_1_strict_100(): void
    {
        self::assertSame(100.0, $this->pollutantsTariff(PollutantCategory::Category1));
    }

    #[Test]
    public function polluants_plus_polluants_strict_500(): void
    {
        self::assertSame(500.0, $this->pollutantsTariff(PollutantCategory::MostPolluting));
    }

    // ============================================================
    // E85 : cas combinés (calcul post-abattement + tarif)
    // L'abattement réduit le CO₂ entrant ; on calcule alors le
    // barème WLTP/NEDC/PA sur la valeur réduite.
    // ============================================================

    #[Test]
    public function e85_wltp_50_reduit_a_30_donne_21_euros(): void
    {
        // 50 × 0,60 = 30 g/km (round) : barème (30-9)*1 = 21 €
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 50]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Wltp);
        self::assertSame(30, $reduced['reduced_co2']);
        self::assertSame(21.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_wltp_100_reduit_a_60_donne_63_euros(): void
    {
        // 100 × 0,60 = 60 g/km : barème 41 + 16 + (60-58)*3 = 63 €
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 100]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Wltp);
        self::assertSame(60, $reduced['reduced_co2']);
        self::assertSame(63.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_wltp_130_reduit_a_78_donne_117_euros(): void
    {
        // 130 × 0,60 = 78 g/km : barème 41 + 16 + (78-58)*3 = 117 €
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 130]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Wltp);
        self::assertSame(78, $reduced['reduced_co2']);
        self::assertSame(117.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_wltp_arrondi_round_half_up_131(): void
    {
        // 131 × 0,60 = 78,6 → round() = 79 g/km
        // barème : 41 + 16 + (79-58)*3 = 120 €
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 131]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Wltp);
        self::assertSame(79, $reduced['reduced_co2'], 'PHP round(78.6) doit donner 79 (half-away-from-zero)');
        self::assertSame(120.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_wltp_250_borne_inclusive_reduit_a_150_donne_1433(): void
    {
        // 250 × 0,60 = 150 g/km : barème (50-9)*1 + (58-50)*2 + (90-58)*3 + (110-90)*4 + (130-110)*10 + (150-130)*50
        // = 41 + 16 + 96 + 80 + 200 + 1000 = 1433 €
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 250]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Wltp);
        self::assertSame(150, $reduced['reduced_co2']);
        self::assertSame(1433.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_wltp_251_perd_l_abattement_tarif_plein(): void
    {
        // 251 > 250 : pas d'abattement : barème 2633 + (251-170)*65 = 7898 €
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 251]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Wltp);
        self::assertSame(251, $reduced['reduced_co2'], 'CO₂ inchangé si pas d\'abattement');
        self::assertSame(7898.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_nedc_100_reduit_a_60_donne_82(): void
    {
        // 100 × 0,60 = 60 g/km NEDC : barème
        // (41-7)*1 + (48-41)*2 + (60-48)*3 = 34 + 14 + 36 = 84 €
        // Wait, vérifier : 60 est entre 48 et 74 (tranche 3 €/g)
        // Tranches NEDC : 0-7 (0€), 7-41 (1€), 41-48 (2€), 48-74 (3€), ...
        // (41-7)*1 + (48-41)*2 + (60-48)*3 = 34 + 14 + 36 = 84 €
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Nedc,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => 100,
        ]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Nedc);
        self::assertSame(60, $reduced['reduced_co2']);
        self::assertSame(84.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_pa_8_reduit_a_6_donne_12750(): void
    {
        // 8 - 2 = 6 CV : barème 12750 €
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 8,
        ]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Pa);
        self::assertSame(6, $reduced['reduced_pa']);
        self::assertSame(12750.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_pa_12_borne_inclusive_reduit_a_10_donne_29750(): void
    {
        // 12 inclusif : 12 - 2 = 10 CV : barème 29750 €
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 12,
        ]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Pa);
        self::assertSame(10, $reduced['reduced_pa']);
        self::assertSame(29750.0, $reduced['tariff']);
    }

    #[Test]
    public function e85_pa_13_perd_l_abattement_tarif_plein(): void
    {
        // 13 > 12 : pas d'abattement : barème 12750 + 3*5000 = 44750 €
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 13,
        ]);
        $reduced = $this->applyE85AndPrice($vfc, HomologationMethod::Pa);
        self::assertSame(13, $reduced['reduced_pa'], 'PA inchangé si pas d\'abattement');
        self::assertSame(44750.0, $reduced['tariff']);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function wltpTariff(int $co2): float
    {
        $rule = new R2025_010_WltpProgressive;
        $vfc = $this->makeVfc(['homologation_method' => HomologationMethod::Wltp, 'co2_wltp' => $co2]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        return $rule->price($context)->co2FullYearTariff ?? -1.0;
    }

    private function nedcTariff(int $co2): float
    {
        $rule = new R2025_011_NedcProgressive;
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Nedc);

        return $rule->price($context)->co2FullYearTariff ?? -1.0;
    }

    private function paTariff(int $cv): float
    {
        $rule = new R2025_012_PaProgressive;
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Pa);

        return $rule->price($context)->co2FullYearTariff ?? -1.0;
    }

    private function pollutantsTariff(PollutantCategory $category): float
    {
        $rule = new R2025_014_PollutantsFlat;
        $vfc = $this->makeVfc([]);
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2025,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedPollutantCategory: $category,
        );

        return $rule->price($context)->pollutantsFullYearTariff ?? -1.0;
    }

    /**
     * Applique l'abattement E85 puis le barème correspondant. Retourne
     * `[reduced_co2 | reduced_pa, tariff]` pour les assertions cas E85.
     *
     * @return array{reduced_co2?: int|null, reduced_pa?: int|null, tariff: float}
     */
    private function applyE85AndPrice(VehicleFiscalCharacteristics $vfc, HomologationMethod $method): array
    {
        $abate = new R2025_023_E85Abatement;
        $context = $this->makeContext($vfc, $method);
        $reducedContext = $abate->abate($context);

        $reducedVfc = $reducedContext->currentFiscalCharacteristics;
        self::assertNotNull($reducedVfc);

        $tariff = match ($method) {
            HomologationMethod::Wltp => (new R2025_010_WltpProgressive)->price($reducedContext)->co2FullYearTariff,
            HomologationMethod::Nedc => (new R2025_011_NedcProgressive)->price($reducedContext)->co2FullYearTariff,
            HomologationMethod::Pa => (new R2025_012_PaProgressive)->price($reducedContext)->co2FullYearTariff,
        };

        return match ($method) {
            HomologationMethod::Wltp => ['reduced_co2' => $reducedVfc->co2_wltp, 'tariff' => $tariff ?? -1.0],
            HomologationMethod::Nedc => ['reduced_co2' => $reducedVfc->co2_nedc, 'tariff' => $tariff ?? -1.0],
            HomologationMethod::Pa => ['reduced_pa' => $reducedVfc->taxable_horsepower, 'tariff' => $tariff ?? -1.0],
        };
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create(array_merge([
            'homologation_method' => HomologationMethod::Wltp,
        ], $overrides));
    }

    private function makeContext(
        VehicleFiscalCharacteristics $vfc,
        HomologationMethod $resolvedMethod,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2025,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $resolvedMethod,
        );
    }
}
