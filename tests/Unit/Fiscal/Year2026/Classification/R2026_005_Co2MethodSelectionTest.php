<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Classification;

use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Classification\R2026_005_Co2MethodSelection;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la cascade de sélection du barème CO₂ 2026 (R-2026-005 + fallback
 * PA documentaire R-2026-006). Reconduction stricte de R-2025-005 ·
 * CIBS L. 421-118 stable.
 */
final class R2026_005_Co2MethodSelectionTest extends TestCase
{
    use RefreshDatabase;

    private R2026_005_Co2MethodSelection $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2026_005_Co2MethodSelection;
    }

    #[Test]
    public function vehicule_wltp_avec_co2_wltp_choisit_wltp(): void
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 100,
            'co2_nedc' => null,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(HomologationMethod::Wltp, $result->resolvedCo2Method);
    }

    #[Test]
    public function vehicule_wltp_sans_co2_wltp_bascule_en_pa_defense_en_profondeur(): void
    {
        $vfc = new VehicleFiscalCharacteristics([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 5,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(HomologationMethod::Pa, $result->resolvedCo2Method);
    }

    #[Test]
    public function vehicule_nedc_avec_co2_nedc_choisit_nedc(): void
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => 130,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(HomologationMethod::Nedc, $result->resolvedCo2Method);
    }

    #[Test]
    public function vehicule_pa_pur_reste_en_pa(): void
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 6,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(HomologationMethod::Pa, $result->resolvedCo2Method);
    }

    #[Test]
    public function classify_attache_le_code_regle_au_contexte(): void
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 100,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertContains('R-2026-005', $result->appliedRuleCodes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeContext(VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vfc->exists ? ($vfc->vehicle ?? Vehicle::factory()->create()) : Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
