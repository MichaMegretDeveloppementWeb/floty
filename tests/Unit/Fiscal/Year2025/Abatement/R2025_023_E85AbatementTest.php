<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Abatement;

use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Goldens BOFiP pour l'abattement E85 (CIBS L. 421-125 réformé).
 *
 * Mécanique testée :
 * - WLTP/NEDC : réduction de 40 % (co2 × 0,60) sauf si > 250 g/km.
 * - PA : soustraction de 2 CV sauf si > 12 CV.
 * - Flag `accepts_e85=false` : aucun effet.
 * - Plafonds inclusifs : 250 g/km exact reste éligible, 251 non.
 *   Idem 12 CV exact éligible, 13 non.
 */
final class R2025_023_E85AbatementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pas_d_abattement_si_flag_e85_est_false(): void
    {
        $vfc = $this->makeVfc(['accepts_e85' => false, 'co2_wltp' => 100]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertSame($context, $result, 'Contexte doit être strictement inchangé si accepts_e85 = false.');
    }

    #[Test]
    public function wltp_co2_100_e85_reduit_a_60(): void
    {
        // 100 × 0,60 = 60 g/km (round(60) = 60)
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 100]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertNotNull($result->currentFiscalCharacteristics);
        self::assertSame(60, $result->currentFiscalCharacteristics->co2_wltp);
    }

    #[Test]
    public function wltp_co2_130_e85_reduit_a_78(): void
    {
        // 130 × 0,60 = 78 g/km
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 130]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertNotNull($result->currentFiscalCharacteristics);
        self::assertSame(78, $result->currentFiscalCharacteristics->co2_wltp);
    }

    #[Test]
    public function wltp_co2_250_borne_inclusive_garde_l_abattement(): void
    {
        // « Sauf lorsque ces émissions dépassent 250 g/km » : « dépassent »
        // = strictement supérieur. 250 exact reste éligible.
        // 250 × 0,60 = 150 g/km
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 250]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertNotNull($result->currentFiscalCharacteristics);
        self::assertSame(150, $result->currentFiscalCharacteristics->co2_wltp);
    }

    #[Test]
    public function wltp_co2_251_perd_l_abattement(): void
    {
        // 251 > 250 : abattement non applicable, contexte inchangé.
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 251]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertSame($context, $result);
    }

    #[Test]
    public function nedc_co2_130_e85_reduit_a_78(): void
    {
        // Même réduction (0,60) pour NEDC.
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Nedc,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => 130,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Nedc);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertNotNull($result->currentFiscalCharacteristics);
        self::assertSame(78, $result->currentFiscalCharacteristics->co2_nedc);
    }

    #[Test]
    public function pa_8_cv_e85_reduit_a_6(): void
    {
        // 8 - 2 = 6 CV
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 8,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Pa);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertNotNull($result->currentFiscalCharacteristics);
        self::assertSame(6, $result->currentFiscalCharacteristics->taxable_horsepower);
    }

    #[Test]
    public function pa_12_cv_borne_inclusive_garde_l_abattement(): void
    {
        // 12 exact reste éligible : 12 - 2 = 10
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 12,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Pa);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertNotNull($result->currentFiscalCharacteristics);
        self::assertSame(10, $result->currentFiscalCharacteristics->taxable_horsepower);
    }

    #[Test]
    public function pa_13_cv_perd_l_abattement(): void
    {
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Pa,
            'accepts_e85' => true,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 13,
        ]);
        $context = $this->makeContext($vfc, HomologationMethod::Pa);

        $result = (new R2025_023_E85Abatement)->abate($context);

        self::assertSame($context, $result);
    }

    #[Test]
    public function ne_modifie_pas_la_vfc_originale(): void
    {
        // Cohérence ADR-0021 : le clone via replicate() doit empêcher
        // toute mutation accidentelle de la VFC persistée.
        $vfc = $this->makeVfc(['accepts_e85' => true, 'co2_wltp' => 130]);
        $context = $this->makeContext($vfc, HomologationMethod::Wltp);

        (new R2025_023_E85Abatement)->abate($context);

        self::assertSame(130, $vfc->co2_wltp);
    }

    #[Test]
    public function rule_code_et_annee_fiscale_corrects(): void
    {
        $rule = new R2025_023_E85Abatement;
        self::assertSame('R-2025-023', $rule->ruleCode());
        self::assertSame(2025, $rule->fiscalYear());
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
