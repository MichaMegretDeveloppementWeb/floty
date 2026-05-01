<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Classification;

use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Classification\R2024_005_Co2MethodSelection;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la cascade de sélection du barème CO₂ (R-2024-005 + R-2024-006
 * fallback PA). La règle :
 *   - homologation WLTP + co2_wltp posé → barème WLTP
 *   - sinon homologation NEDC + co2_nedc posé → barème NEDC
 *   - sinon → barème PA (fallback R-2024-006)
 *
 * Les bordures testées correspondent aux situations réelles de la
 * flotte : véhicule WLTP avec valeur manquante (saisie incomplète),
 * véhicule NEDC sans valeur (vieux import), véhicule PA pur (avant
 * 2002 — pas de mesure CO₂).
 */
final class R2024_005_Co2MethodSelectionTest extends TestCase
{
    use RefreshDatabase;

    private R2024_005_Co2MethodSelection $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_005_Co2MethodSelection;
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
        // Le CHECK SQL `chk_vfc_homologation_implies_measurement` garantit
        // qu'aucune VFC persistée ne peut avoir `WLTP` + `co2_wltp NULL`,
        // mais on teste ici la défense en profondeur du code de la règle
        // (cas race / hydratation manuelle hors BDD).
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
    public function vehicule_nedc_sans_co2_nedc_bascule_en_pa_defense_en_profondeur(): void
    {
        // Cf. note du test précédent : CHECK SQL exclut ce cas en BDD,
        // mais la défense en profondeur du code reste testée.
        $vfc = new VehicleFiscalCharacteristics([
            'homologation_method' => HomologationMethod::Nedc,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 7,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(HomologationMethod::Pa, $result->resolvedCo2Method);
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

        self::assertContains('R-2024-005', $result->appliedRuleCodes);
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
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
