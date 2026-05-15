<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\CrossYear;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use App\Fiscal\Year2024\Pricing\R2024_014_PollutantsFlat;
use App\Fiscal\Year2026\Pricing\R2026_010_WltpProgressive;
use App\Fiscal\Year2026\Pricing\R2026_011_NedcProgressive;
use App\Fiscal\Year2026\Pricing\R2026_012_PaProgressive;
use App\Fiscal\Year2026\Pricing\R2026_014bis_PollutantsFlat;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Φ.3 · invariants cross-année 2024 → 2026 (saut direct).
 *
 * Vérifie le **durcissement cumulé** sur 2 ans · les barèmes 2026
 * sont strictement supérieurs à 2024 sur tous les profils CO₂.
 *
 * **Évolutions cumulées (LF 2024 art. 97-19° puis 20°)** ·
 *   - WLTP 100 g · 173 € (2024) → 213 € (2026) · **+23,1 %**
 *   - NEDC 100 g · 264 € (2024 calculé) → 324 € (2026) · **+22,7 %**
 *   - PA 10 CV · 26 250 € (2024) → 33 000 € (2026) · **+25,7 %**
 *
 * **Polluants au 01/03/2026** (LF 2026 art. 58 V IV) ·
 *   - Cat1 · 100 € (2024) → 130 € (2026 bis) · +30 %
 *   - MostPolluting · 500 € (2024) → 650 € (2026 bis) · +30 %
 *   - E inchangé · 0 €
 */
final class CrossYear2024To2026DirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function wltp_100g_passe_de_173_a_213_euros_entre_2024_et_2026(): void
    {
        $t2024 = $this->wltp2024(100);
        $t2026 = $this->wltp2026(100);

        self::assertSame(173.0, $t2024);
        self::assertSame(213.0, $t2026);
        self::assertGreaterThan($t2024, $t2026, 'Durcissement 2024→2026 sur WLTP 100g.');
    }

    #[Test]
    public function nedc_100g_passe_de_264_a_324_euros_entre_2024_et_2026(): void
    {
        // 2024 NEDC 100g · (45-12)*1 + (52-45)*2 + (79-52)*3 + (95-79)*4 + (100-95)*10
        // = 33 + 14 + 81 + 64 + 50 = 242 (recalcul tranche par tranche)
        // Note · les tranches NEDC 2024 sont (0,12](0), (12,45](1), (45,52](2), (52,79](3), (79,95](4), (95,112](10), (112,128](50), (128,145](60), (145,+∞)(65)
        // Donc 100g · (45-12)*1 + (52-45)*2 + (79-52)*3 + (95-79)*4 + (100-95)*10
        //          = 33 + 14 + 81 + 64 + 50 = 242 €
        $t2024 = $this->nedc2024(100);
        $t2026 = $this->nedc2026(100);

        self::assertSame(242.0, $t2024);
        self::assertSame(324.0, $t2026);
        self::assertGreaterThan($t2024, $t2026);
    }

    #[Test]
    public function pa_10cv_passe_de_26250_a_33000_euros_entre_2024_et_2026(): void
    {
        // PA 2024 · 3×1500 + 3×2250 + 4×3750 = 4500 + 6750 + 15000 = 26250
        // PA 2026 · 3×2000 + 3×3000 + 4×4500 = 6000 + 9000 + 18000 = 33000
        $t2024 = $this->pa2024(10);
        $t2026 = $this->pa2026(10);

        self::assertSame(26250.0, $t2024);
        self::assertSame(33000.0, $t2026);
        self::assertGreaterThan($t2024, $t2026);
    }

    #[Test]
    public function polluants_cat1_passe_de_100_a_130_euros_apres_01_03_2026(): void
    {
        // 2024 v stable · 100 €
        $t2024 = $this->pollutants2024(PollutantCategory::Category1);
        // 2026 v-bis (01/03 → 31/12) · 130 €
        $t2026Bis = $this->pollutants2026Bis(PollutantCategory::Category1);

        self::assertSame(100.0, $t2024);
        self::assertSame(130.0, $t2026Bis);
        self::assertEqualsWithDelta($t2024 * 1.30, $t2026Bis, 0.01);
    }

    #[Test]
    public function polluants_mostpolluting_passe_de_500_a_650_euros_apres_01_03_2026(): void
    {
        $t2024 = $this->pollutants2024(PollutantCategory::MostPolluting);
        $t2026Bis = $this->pollutants2026Bis(PollutantCategory::MostPolluting);

        self::assertSame(500.0, $t2024);
        self::assertSame(650.0, $t2026Bis);
        self::assertEqualsWithDelta($t2024 * 1.30, $t2026Bis, 0.01);
    }

    #[Test]
    public function polluants_e_inchanges_2024_et_2026(): void
    {
        // Catégorie E (électrique / hydrogène) · 0 € invariant
        self::assertSame(0.0, $this->pollutants2024(PollutantCategory::E));
        self::assertSame(0.0, $this->pollutants2026Bis(PollutantCategory::E));
    }

    #[Test]
    public function invariant_durcissement_strict_sur_tous_co2_wltp_significatifs(): void
    {
        // Pour chaque CO₂ entre 50 et 200 (par pas de 25),
        // 2026 doit être ≥ 2024 (durcissement strict, jamais d'allégement).
        foreach ([50, 75, 100, 125, 150, 175, 200] as $co2) {
            $t2024 = $this->wltp2024($co2);
            $t2026 = $this->wltp2026($co2);
            self::assertGreaterThanOrEqual(
                $t2024,
                $t2026,
                sprintf('Invariant durcissement violé · WLTP %d g · 2024=%s vs 2026=%s', $co2, $t2024, $t2026),
            );
        }
    }

    private function wltp2024(int $co2): float
    {
        return $this->priceWltp(new R2024_010_WltpProgressive, $co2, 2024);
    }

    private function wltp2026(int $co2): float
    {
        return $this->priceWltp(new R2026_010_WltpProgressive, $co2, 2026);
    }

    private function nedc2024(int $co2): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
        ]);

        return (new R2024_011_NedcProgressive)->price($this->makeContext($vfc, 2024, HomologationMethod::Nedc))->co2FullYearTariff ?? -1.0;
    }

    private function nedc2026(int $co2): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
        ]);

        return (new R2026_011_NedcProgressive)->price($this->makeContext($vfc, 2026, HomologationMethod::Nedc))->co2FullYearTariff ?? -1.0;
    }

    private function pa2024(int $cv): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);

        return (new R2024_012_PaProgressive)->price($this->makeContext($vfc, 2024, HomologationMethod::Pa))->co2FullYearTariff ?? -1.0;
    }

    private function pa2026(int $cv): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);

        return (new R2026_012_PaProgressive)->price($this->makeContext($vfc, 2026, HomologationMethod::Pa))->co2FullYearTariff ?? -1.0;
    }

    private function pollutants2024(PollutantCategory $cat): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create();

        return (new R2024_014_PollutantsFlat)->price(new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
            resolvedPollutantCategory: $cat,
        ))->pollutantsFullYearTariff ?? -1.0;
    }

    private function pollutants2026Bis(PollutantCategory $cat): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create();

        return (new R2026_014bis_PollutantsFlat)->price(new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedPollutantCategory: $cat,
        ))->pollutantsFullYearTariff ?? -1.0;
    }

    private function priceWltp(R2024_010_WltpProgressive|R2026_010_WltpProgressive $rule, int $co2, int $year): float
    {
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
        ]);

        return $rule->price($this->makeContext($vfc, $year, HomologationMethod::Wltp))->co2FullYearTariff ?? -1.0;
    }

    private function makeContext(VehicleFiscalCharacteristics $vfc, int $year, HomologationMethod $method): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: $year,
            daysInYear: $year % 4 === 0 ? 366 : 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $method,
        );
    }
}
