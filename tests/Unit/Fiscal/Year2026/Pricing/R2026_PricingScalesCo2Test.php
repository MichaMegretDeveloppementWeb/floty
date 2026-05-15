<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Pricing;

use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Pricing\R2026_010_WltpProgressive;
use App\Fiscal\Year2026\Pricing\R2026_011_NedcProgressive;
use App\Fiscal\Year2026\Pricing\R2026_012_PaProgressive;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Goldens au centime près pour les barèmes progressifs CO₂ 2026
 * (R-2026-010 WLTP, R-2026-011 NEDC, R-2026-012 PA).
 *
 * **Durcissement majeur 2026** vs 2025 · LF 2024 art. 97-20° par
 * anticipation programmée au 01/01/2026. Toute régression accidentelle
 * d'une borne ou d'un tarif marginal sera capturée ici.
 *
 * **Valeurs cibles WLTP 2026** · 100 g/km = 213 € (vs 193 € en 2025,
 * +10,4 %). Calcul tranche par tranche documenté en commentaires.
 *
 * **Valeurs cibles NEDC 2026** · 100 g/km = 324 € (vs 284 € en 2025,
 * +14,1 %).
 *
 * **Valeurs cibles PA 2026** · 10 CV = 33 000 € (vs 29 750 € en 2025,
 * +10,9 %).
 */
final class R2026_PricingScalesCo2Test extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // R-2026-010 WLTP · barème durci 2026
    // Tranches · (0,4](0) · (4,45](1) · (45,53](2) · (53,85](3) ·
    // (85,105](4) · (105,125](10) · (125,145](50) · (145,165](60) ·
    // (165,+∞)(65)
    // ============================================================

    #[Test]
    public function wltp_co2_0_donne_tarif_zero(): void
    {
        self::assertSame(0.0, $this->wltpTariff(0));
    }

    #[Test]
    public function wltp_co2_4_borne_basse_premiere_tranche_taxee_reste_zero(): void
    {
        // Tranche (0,4] inclusive · 4 lui-même tombe dans la tranche zéro.
        self::assertSame(0.0, $this->wltpTariff(4));
    }

    #[Test]
    public function wltp_co2_100_donne_213_euros_durcissement_2026(): void
    {
        // (45-4)*1 + (53-45)*2 + (85-53)*3 + (100-85)*4
        // = 41 + 16 + 96 + 60 = 213 € (+20 € vs 193 € en 2025).
        self::assertSame(213.0, $this->wltpTariff(100));
    }

    #[Test]
    public function wltp_co2_105_borne_haute_tranche_4(): void
    {
        // 41 + 16 + 96 + (105-85)*4 = 41 + 16 + 96 + 80 = 233 €
        self::assertSame(233.0, $this->wltpTariff(105));
    }

    #[Test]
    public function wltp_co2_125_borne_haute_tranche_10(): void
    {
        // 233 + (125-105)*10 = 233 + 200 = 433 €
        self::assertSame(433.0, $this->wltpTariff(125));
    }

    #[Test]
    public function wltp_co2_145_capture_le_palier_50(): void
    {
        // 433 + (145-125)*50 = 433 + 1000 = 1433 €
        self::assertSame(1433.0, $this->wltpTariff(145));
    }

    #[Test]
    public function wltp_co2_165_borne_haute_avant_tranche_ouverte(): void
    {
        // 1433 + (165-145)*60 = 1433 + 1200 = 2633 €
        self::assertSame(2633.0, $this->wltpTariff(165));
    }

    #[Test]
    public function wltp_co2_200_dans_la_tranche_ouverte(): void
    {
        // 2633 + (200-165)*65 = 2633 + 2275 = 4908 €
        self::assertSame(4908.0, $this->wltpTariff(200));
    }

    // ============================================================
    // R-2026-011 NEDC · barème durci 2026
    // Tranches · (0,3](0) · (3,37](1) · (37,44](2) · (44,70](3) ·
    // (70,87](4) · (87,103](10) · (103,120](50) · (120,136](60) ·
    // (136,+∞)(65)
    // ============================================================

    #[Test]
    public function nedc_co2_100_donne_324_euros_durcissement_2026(): void
    {
        // (37-3)*1 + (44-37)*2 + (70-44)*3 + (87-70)*4 + (100-87)*10
        // = 34 + 14 + 78 + 68 + 130 = 324 € (+40 € vs 284 € en 2025).
        self::assertSame(324.0, $this->nedcTariff(100));
    }

    #[Test]
    public function nedc_co2_120_borne_haute_tranche_50(): void
    {
        // 34 + 14 + 78 + 68 + (103-87)*10 + (120-103)*50
        // = 34 + 14 + 78 + 68 + 160 + 850 = 1204 €
        self::assertSame(1204.0, $this->nedcTariff(120));
    }

    #[Test]
    public function nedc_co2_136_borne_haute_avant_ouverte(): void
    {
        // 1204 + (136-120)*60 = 1204 + 960 = 2164 €
        self::assertSame(2164.0, $this->nedcTariff(136));
    }

    // ============================================================
    // R-2026-012 PA · barème durci 2026
    // Tranches · (0,3](2000/CV) · (3,6](3000) · (6,10](4500) ·
    // (10,15](5250) · (15,+∞)(6500)
    // ============================================================

    #[Test]
    public function pa_3_cv_donne_6000_euros(): void
    {
        // 3 * 2000 = 6000 €
        self::assertSame(6000.0, $this->paTariff(3));
    }

    #[Test]
    public function pa_6_cv_donne_15000_euros(): void
    {
        // 6000 + 3*3000 = 6000 + 9000 = 15000 €
        self::assertSame(15000.0, $this->paTariff(6));
    }

    #[Test]
    public function pa_10_cv_donne_33000_euros_durcissement_2026(): void
    {
        // 15000 + 4*4500 = 15000 + 18000 = 33000 € (+3 250 € vs 29 750 € en 2025).
        self::assertSame(33000.0, $this->paTariff(10));
    }

    #[Test]
    public function pa_15_cv_capture_le_palier_5250(): void
    {
        // 33000 + 5*5250 = 33000 + 26250 = 59250 €
        self::assertSame(59250.0, $this->paTariff(15));
    }

    #[Test]
    public function pa_20_cv_dans_la_tranche_ouverte(): void
    {
        // 59250 + 5*6500 = 59250 + 32500 = 91750 €
        self::assertSame(91750.0, $this->paTariff(20));
    }

    private function wltpTariff(int $co2): float
    {
        $rule = new R2026_010_WltpProgressive;
        $vfc = $this->makeVfc(['homologation_method' => HomologationMethod::Wltp, 'co2_wltp' => $co2]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        return $rule->price($context)->co2FullYearTariff ?? -1.0;
    }

    private function nedcTariff(int $co2): float
    {
        $rule = new R2026_011_NedcProgressive;
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
        $rule = new R2026_012_PaProgressive;
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Pa);

        return $rule->price($context)->co2FullYearTariff ?? -1.0;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeContext(
        VehicleFiscalCharacteristics $vfc,
        HomologationMethod $resolvedMethod,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $resolvedMethod,
        );
    }
}
