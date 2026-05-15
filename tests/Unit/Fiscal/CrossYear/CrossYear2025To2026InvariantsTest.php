<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\CrossYear;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Pricing\R2025_010_WltpProgressive;
use App\Fiscal\Year2025\Pricing\R2025_011_NedcProgressive;
use App\Fiscal\Year2025\Pricing\R2025_012_PaProgressive;
use App\Fiscal\Year2025\Pricing\R2025_014_PollutantsFlat;
use App\Fiscal\Year2026\Pricing\R2026_010_WltpProgressive;
use App\Fiscal\Year2026\Pricing\R2026_011_NedcProgressive;
use App\Fiscal\Year2026\Pricing\R2026_012_PaProgressive;
use App\Fiscal\Year2026\Pricing\R2026_014_PollutantsFlat;
use App\Fiscal\Year2026\Pricing\R2026_014bis_PollutantsFlat;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'invariants cross-année 2025 → 2026.
 *
 * Vérifie les transitions matérielles entre les barèmes 2025 et 2026
 * et garantit que les durcissements + revalorisations 2026 sont
 * cohérents avec les attendus législatifs ·
 *
 * - **Durcissement CO₂** · LF 2024 art. 97-20° (anticipation
 *   programmée au 01/01/2026) · WLTP +10,4 %, NEDC +14,1 %, PA +10,9 %.
 * - **Revalorisation polluants** · LF 2026 art. 58 (V), IV
 *   (LOI n° 2026-103 du 19/02/2026, effet 01/03/2026) ·
 *   E inchangé (0 €), Cat1 +30 %, MostPolluting +30 %.
 *
 * **Garde-fou** · si quelqu'un modifie accidentellement un barème 2025
 * ou 2026 en cassant l'invariant cross-année, ces tests sautent.
 */
final class CrossYear2025To2026InvariantsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function wltp_100g_km_passe_de_193_euros_en_2025_a_213_euros_en_2026_durcissement_10_4_pct(): void
    {
        $tariff2025 = $this->wltpTariff2025(100);
        $tariff2026 = $this->wltpTariff2026(100);

        self::assertSame(193.0, $tariff2025);
        self::assertSame(213.0, $tariff2026);
        // Durcissement +10,4 % approximatif (20 / 193 ≈ 10,36 %).
        self::assertEqualsWithDelta(0.1036, ($tariff2026 - $tariff2025) / $tariff2025, 0.0005);
    }

    #[Test]
    public function nedc_100g_km_passe_de_284_euros_en_2025_a_324_euros_en_2026_durcissement_14_1_pct(): void
    {
        $tariff2025 = $this->nedcTariff2025(100);
        $tariff2026 = $this->nedcTariff2026(100);

        self::assertSame(284.0, $tariff2025);
        self::assertSame(324.0, $tariff2026);
        // Durcissement +14,1 % approximatif (40 / 284 ≈ 14,08 %).
        self::assertEqualsWithDelta(0.1408, ($tariff2026 - $tariff2025) / $tariff2025, 0.0005);
    }

    #[Test]
    public function pa_10_cv_passe_de_29750_euros_en_2025_a_33000_euros_en_2026_durcissement_10_9_pct(): void
    {
        $tariff2025 = $this->paTariff2025(10);
        $tariff2026 = $this->paTariff2026(10);

        self::assertSame(29750.0, $tariff2025);
        self::assertSame(33000.0, $tariff2026);
        // Durcissement +10,9 % approximatif (3 250 / 29 750 ≈ 10,92 %).
        self::assertEqualsWithDelta(0.1092, ($tariff2026 - $tariff2025) / $tariff2025, 0.0005);
    }

    #[Test]
    public function polluants_cat1_est_constant_100_euros_en_2025_et_en_2026_jusqu_au_28_02(): void
    {
        // R-2025-014 = 100 € constant toute l'année 2025.
        // R-2026-014 (v 01/01-28/02/2026) = 100 € (hérité 2025).
        // R-2026-014-bis (v 01/03-31/12/2026) = 130 € (+30 % LF 2026 art. 58).
        $v2025 = $this->pollutantsTariff2025(PollutantCategory::Category1);
        $v2026v1 = $this->pollutantsTariff2026V1(PollutantCategory::Category1);
        $v2026bis = $this->pollutantsTariff2026Bis(PollutantCategory::Category1);

        self::assertSame(100.0, $v2025);
        self::assertSame(100.0, $v2026v1);
        self::assertSame(130.0, $v2026bis);
    }

    #[Test]
    public function polluants_mostpolluting_passe_de_500_a_650_apres_le_01_03_2026_plus_30_pct(): void
    {
        $v2025 = $this->pollutantsTariff2025(PollutantCategory::MostPolluting);
        $v2026v1 = $this->pollutantsTariff2026V1(PollutantCategory::MostPolluting);
        $v2026bis = $this->pollutantsTariff2026Bis(PollutantCategory::MostPolluting);

        self::assertSame(500.0, $v2025);
        self::assertSame(500.0, $v2026v1);
        self::assertSame(650.0, $v2026bis);
        // Invariant +30 % entre v1 (=2025) et bis 2026.
        self::assertEqualsWithDelta($v2025 * 1.30, $v2026bis, 0.01);
    }

    #[Test]
    public function polluants_electrique_reste_zero_en_2025_2026_v1_et_2026_bis(): void
    {
        // L'exonération CO₂ + catégorie E sont stables · jamais de
        // revalorisation pour les véhicules à émissions nulles.
        self::assertSame(0.0, $this->pollutantsTariff2025(PollutantCategory::E));
        self::assertSame(0.0, $this->pollutantsTariff2026V1(PollutantCategory::E));
        self::assertSame(0.0, $this->pollutantsTariff2026Bis(PollutantCategory::E));
    }

    private function wltpTariff2025(int $co2): float
    {
        $vfc = $this->makeVfc(['homologation_method' => HomologationMethod::Wltp, 'co2_wltp' => $co2]);

        return (new R2025_010_WltpProgressive)->price($this->makeCo2Context($vfc, 2025, HomologationMethod::Wltp))->co2FullYearTariff ?? -1.0;
    }

    private function wltpTariff2026(int $co2): float
    {
        $vfc = $this->makeVfc(['homologation_method' => HomologationMethod::Wltp, 'co2_wltp' => $co2]);

        return (new R2026_010_WltpProgressive)->price($this->makeCo2Context($vfc, 2026, HomologationMethod::Wltp))->co2FullYearTariff ?? -1.0;
    }

    private function nedcTariff2025(int $co2): float
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
        ]);

        return (new R2025_011_NedcProgressive)->price($this->makeCo2Context($vfc, 2025, HomologationMethod::Nedc))->co2FullYearTariff ?? -1.0;
    }

    private function nedcTariff2026(int $co2): float
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => $co2,
        ]);

        return (new R2026_011_NedcProgressive)->price($this->makeCo2Context($vfc, 2026, HomologationMethod::Nedc))->co2FullYearTariff ?? -1.0;
    }

    private function paTariff2025(int $cv): float
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);

        return (new R2025_012_PaProgressive)->price($this->makeCo2Context($vfc, 2025, HomologationMethod::Pa))->co2FullYearTariff ?? -1.0;
    }

    private function paTariff2026(int $cv): float
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => $cv,
        ]);

        return (new R2026_012_PaProgressive)->price($this->makeCo2Context($vfc, 2026, HomologationMethod::Pa))->co2FullYearTariff ?? -1.0;
    }

    private function pollutantsTariff2025(PollutantCategory $category): float
    {
        return (new R2025_014_PollutantsFlat)->price($this->makePollutantsContext(2025, $category))->pollutantsFullYearTariff ?? -1.0;
    }

    private function pollutantsTariff2026V1(PollutantCategory $category): float
    {
        return (new R2026_014_PollutantsFlat)->price($this->makePollutantsContext(2026, $category))->pollutantsFullYearTariff ?? -1.0;
    }

    private function pollutantsTariff2026Bis(PollutantCategory $category): float
    {
        return (new R2026_014bis_PollutantsFlat)->price($this->makePollutantsContext(2026, $category))->pollutantsFullYearTariff ?? -1.0;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeCo2Context(
        VehicleFiscalCharacteristics $vfc,
        int $year,
        HomologationMethod $resolvedMethod,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: $year,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $resolvedMethod,
        );
    }

    private function makePollutantsContext(int $year, PollutantCategory $category): PipelineContext
    {
        $vfc = $this->makeVfc([]);

        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: $year,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedPollutantCategory: $category,
        );
    }
}
