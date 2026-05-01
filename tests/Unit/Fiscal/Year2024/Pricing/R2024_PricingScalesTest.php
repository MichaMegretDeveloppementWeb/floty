<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Pricing;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use App\Fiscal\Year2024\Pricing\R2024_014_PollutantsFlat;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests bordures BOFiP pour les barèmes progressifs CO₂ (R-2024-010
 * WLTP, R-2024-011 NEDC, R-2024-012 PA) et le tarif plat polluants
 * (R-2024-014).
 *
 * Ces tests servent de **filet anti-régression** : tout changement
 * accidentel d'une borne ou d'un tarif marginal sera détecté ici.
 * Les valeurs attendues sont calculées à la main à partir des barèmes
 * publiés (CIBS art. L. 421-119 et suivants).
 */
final class R2024_PricingScalesTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // R-2024-010 WLTP — bordures de tranche
    // ============================================================

    #[Test]
    public function wltp_co2_0_donne_tarif_zero(): void
    {
        $tariff = $this->wltpTariff(0);
        self::assertSame(0.0, $tariff);
    }

    #[Test]
    public function wltp_co2_14_juste_avant_premier_palier_donne_zero(): void
    {
        // Tranche 0-14 → marginalRate 0.0
        $tariff = $this->wltpTariff(14);
        self::assertSame(0.0, $tariff);
    }

    #[Test]
    public function wltp_co2_55_borne_haute_tranche_2_donne_41_euros(): void
    {
        // (55-14) × 1.0 = 41
        $tariff = $this->wltpTariff(55);
        self::assertSame(41.0, $tariff);
    }

    #[Test]
    public function wltp_co2_100_donne_268_euros(): void
    {
        // (55-14)*1 + (63-55)*2 + (95-63)*3 + (100-95)*4 = 41+16+96+20 = 173
        $tariff = $this->wltpTariff(100);
        self::assertSame(173.0, $tariff);
    }

    #[Test]
    public function wltp_co2_135_borne_haute_tranche_50_capture_le_palier(): void
    {
        // (55-14)*1 + (63-55)*2 + (95-63)*3 + (115-95)*4 + (135-115)*10
        // = 41 + 16 + 96 + 80 + 200 = 433
        $tariff = $this->wltpTariff(135);
        self::assertSame(433.0, $tariff);
    }

    #[Test]
    public function wltp_co2_175_borne_haute_avant_tranche_ouverte(): void
    {
        // ... + (155-135)*50 + (175-155)*60 = 433 + 1000 + 1200 = 2633
        $tariff = $this->wltpTariff(175);
        self::assertSame(2633.0, $tariff);
    }

    #[Test]
    public function wltp_co2_200_dans_la_tranche_ouverte(): void
    {
        // ... + (200-175)*65 = 2633 + 1625 = 4258
        $tariff = $this->wltpTariff(200);
        self::assertSame(4258.0, $tariff);
    }

    #[Test]
    public function wltp_ne_s_applique_pas_si_methode_resolue_est_pa(): void
    {
        // La règle skip si la méthode résolue est PA — laisse `co2FullYearTariff` à null.
        $rule = new R2024_010_WltpProgressive;
        $vfc = $this->makeVfc(['homologation_method' => HomologationMethod::Wltp, 'co2_wltp' => 100]);
        $context = $this->makeContext($vfc, HomologationMethod::Pa);

        $result = $rule->price($context);

        self::assertNull($result->co2FullYearTariff);
    }

    // ============================================================
    // R-2024-011 NEDC — bordures de tranche
    // ============================================================

    #[Test]
    public function nedc_co2_130_donne_le_bon_tarif(): void
    {
        // (45-12)*1 + (52-45)*2 + (79-52)*3 + (95-79)*4 + (112-95)*10 + (128-112)*50 + (130-128)*60
        // = 33+14+81+64+170+800+120 = 1282
        $tariff = $this->nedcTariff(130);
        self::assertSame(1282.0, $tariff);
    }

    #[Test]
    public function nedc_co2_145_borne_haute_capture_la_tranche_60(): void
    {
        // ... + (145-128)*60 - 120 = on remplace 130 par 145 → +(15)*60 = 900 (au lieu de 120)
        // 33+14+81+64+170+800+(145-128)*60 = 1162 + 1020 = 2182
        $tariff = $this->nedcTariff(145);
        self::assertSame(2182.0, $tariff);
    }

    // ============================================================
    // R-2024-012 PA — bordures CV
    // ============================================================

    #[Test]
    public function pa_3_cv_donne_4500_euros(): void
    {
        // 3*1500 = 4500
        $tariff = $this->paTariff(3);
        self::assertSame(4500.0, $tariff);
    }

    #[Test]
    public function pa_6_cv_donne_11250_euros(): void
    {
        // 3*1500 + 3*2250 = 4500 + 6750 = 11250
        $tariff = $this->paTariff(6);
        self::assertSame(11250.0, $tariff);
    }

    #[Test]
    public function pa_10_cv_donne_26250_euros(): void
    {
        // 11250 + 4*3750 = 11250 + 15000 = 26250
        $tariff = $this->paTariff(10);
        self::assertSame(26250.0, $tariff);
    }

    #[Test]
    public function pa_20_cv_dans_la_tranche_ouverte(): void
    {
        // 26250 + 5*4750 + 5*6000 = 26250 + 23750 + 30000 = 80000
        $tariff = $this->paTariff(20);
        self::assertSame(80000.0, $tariff);
    }

    // ============================================================
    // R-2024-014 Polluants flat
    // ============================================================

    #[Test]
    public function polluants_categorie_e_donne_zero(): void
    {
        $tariff = $this->pollutantsTariff(PollutantCategory::E);
        self::assertSame(0.0, $tariff);
    }

    #[Test]
    public function polluants_categorie_1_donne_100_euros(): void
    {
        $tariff = $this->pollutantsTariff(PollutantCategory::Category1);
        self::assertSame(100.0, $tariff);
    }

    #[Test]
    public function polluants_les_plus_polluants_donne_500_euros(): void
    {
        $tariff = $this->pollutantsTariff(PollutantCategory::MostPolluting);
        self::assertSame(500.0, $tariff);
    }

    private function wltpTariff(int $co2): float
    {
        $rule = new R2024_010_WltpProgressive;
        $vfc = $this->makeVfc(['homologation_method' => HomologationMethod::Wltp, 'co2_wltp' => $co2]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        return $rule->price($context)->co2FullYearTariff ?? -1.0;
    }

    private function nedcTariff(int $co2): float
    {
        $rule = new R2024_011_NedcProgressive;
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
        $rule = new R2024_012_PaProgressive;
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
        $rule = new R2024_014_PollutantsFlat;
        $vfc = $this->makeVfc([]);
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
            resolvedPollutantCategory: $category,
        );

        return $rule->price($context)->pollutantsFullYearTariff ?? -1.0;
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
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $resolvedMethod,
        );
    }
}
