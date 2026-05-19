<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Pricing;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Pricing\R2025_010_WltpProgressive;
use App\Fiscal\Year2025\Pricing\R2025_011_NedcProgressive;
use App\Fiscal\Year2025\Pricing\R2025_012_PaProgressive;
use App\Fiscal\Year2025\Pricing\R2025_014_PollutantsFlat;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Goldens BOFiP au centime près pour les barèmes progressifs CO₂ 2025
 * (R-2025-010 WLTP, R-2025-011 NEDC, R-2025-012 PA) et le tarif plat
 * polluants (R-2025-014). Référence BOFiP BOI-AIS-MOB-10-30-20-20250528.
 */
final class R2025_PricingScalesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function wltp_co2_0_donne_tarif_zero(): void
    {
        self::assertSame(0.0, $this->wltpTariff(0));
    }

    #[Test]
    public function wltp_co2_9_borne_basse_premiere_tranche_taxee_reste_zero(): void
    {
        // Tranche 0-9 exclusive : 9 reste dans la tranche zéro
        // (lowerExclusive sémantique BracketRange).
        self::assertSame(0.0, $this->wltpTariff(9));
    }

    #[Test]
    public function wltp_co2_100_donne_193_euros_exemple_bofip(): void
    {
        // (50-9)*1 + (58-50)*2 + (90-58)*3 + (100-90)*4
        // = 41 + 16 + 96 + 40 = 193 €
        self::assertSame(193.0, $this->wltpTariff(100));
    }

    #[Test]
    public function wltp_co2_130_donne_433_euros(): void
    {
        // 193 + (110-100)*4 + (130-110)*10 = 193 + 40 + 200 = 433 €
        self::assertSame(433.0, $this->wltpTariff(130));
    }

    #[Test]
    public function wltp_co2_150_capture_le_palier_50(): void
    {
        // 41 + 16 + 96 + 80 + 200 + (150-130)*50 = 433 + 1000 = 1433 €
        self::assertSame(1433.0, $this->wltpTariff(150));
    }

    #[Test]
    public function wltp_co2_170_borne_haute_avant_tranche_ouverte(): void
    {
        // 1433 + (170-150)*60 = 1433 + 1200 = 2633 €
        self::assertSame(2633.0, $this->wltpTariff(170));
    }

    #[Test]
    public function wltp_co2_200_dans_la_tranche_ouverte(): void
    {
        // 2633 + (200-170)*65 = 2633 + 1950 = 4583 €
        self::assertSame(4583.0, $this->wltpTariff(200));
    }

    #[Test]
    public function nedc_co2_100_donne_284_euros(): void
    {
        // (41-7)*1 + (48-41)*2 + (74-48)*3 + (91-74)*4 + (100-91)*10
        // = 34 + 14 + 78 + 68 + 90 = 284 €
        self::assertSame(284.0, $this->nedcTariff(100));
    }

    #[Test]
    public function nedc_co2_124_borne_haute_tranche_10(): void
    {
        // 34 + 14 + 78 + 68 + (107-91)*10 + (124-107)*50
        // = 34 + 14 + 78 + 68 + 160 + 850 = 1204 €
        self::assertSame(1204.0, $this->nedcTariff(124));
    }

    #[Test]
    public function nedc_co2_140_borne_haute_avant_ouverte(): void
    {
        // 1204 + (140-124)*60 = 1204 + 960 = 2164 €
        self::assertSame(2164.0, $this->nedcTariff(140));
    }

    #[Test]
    public function pa_3_cv_donne_5250_euros(): void
    {
        // 3 * 1750 = 5250 €
        self::assertSame(5250.0, $this->paTariff(3));
    }

    #[Test]
    public function pa_6_cv_donne_12750_euros(): void
    {
        // 5250 + 3*2500 = 5250 + 7500 = 12750 €
        self::assertSame(12750.0, $this->paTariff(6));
    }

    #[Test]
    public function pa_10_cv_donne_29750_euros(): void
    {
        // 12750 + 4*4250 = 12750 + 17000 = 29750 €
        self::assertSame(29750.0, $this->paTariff(10));
    }

    #[Test]
    public function pa_15_cv_capture_le_palier_5000(): void
    {
        // 29750 + 5*5000 = 29750 + 25000 = 54750 €
        self::assertSame(54750.0, $this->paTariff(15));
    }

    #[Test]
    public function pa_20_cv_dans_la_tranche_ouverte(): void
    {
        // 54750 + 5*6250 = 54750 + 31250 = 86000 €
        self::assertSame(86000.0, $this->paTariff(20));
    }

    #[Test]
    public function polluants_categorie_e_donne_zero(): void
    {
        self::assertSame(0.0, $this->pollutantsTariff(PollutantCategory::E));
    }

    #[Test]
    public function polluants_categorie_1_donne_100_euros(): void
    {
        self::assertSame(100.0, $this->pollutantsTariff(PollutantCategory::Category1));
    }

    #[Test]
    public function polluants_les_plus_polluants_donne_500_euros(): void
    {
        self::assertSame(500.0, $this->pollutantsTariff(PollutantCategory::MostPolluting));
    }

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
            fiscalYear: 2025,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $resolvedMethod,
        );
    }
}
