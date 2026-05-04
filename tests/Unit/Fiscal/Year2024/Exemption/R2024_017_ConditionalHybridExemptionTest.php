<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Exemption;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Exemption\R2024_017_ConditionalHybridExemption;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les seuils d'exonération hybride 2024 (CIBS L. 421-125).
 * Régimes :
 *   - général (≥ 3 ans au 01/01/2024) : WLTP ≤ 60, NEDC ≤ 50, PA ≤ 3 CV
 *   - aménagé (< 3 ans)              : WLTP ≤ 120, NEDC ≤ 100, PA ≤ 6 CV
 *
 * Bordures testées : ≤ vs > seuil pour chaque méthode + chaque régime.
 * Cette règle exonère uniquement la taxe CO₂ (pas polluants).
 */
final class R2024_017_ConditionalHybridExemptionTest extends TestCase
{
    use RefreshDatabase;

    private R2024_017_ConditionalHybridExemption $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_017_ConditionalHybridExemption;
    }

    #[Test]
    public function vehicule_essence_pur_n_est_pas_eligible_meme_si_faible_co2(): void
    {
        // L'éligibilité demande une combinaison hybride avec sous-jacent
        // essence ; un véhicule essence pur ne tombe pas dans la règle.
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Gasoline,
            'underlying_combustion_engine_type' => null,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 50,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 5));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_wltp_50_3_ans_donne_exoneration_co2_general(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 50,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 4));

        self::assertTrue($verdict->isExempt);
        self::assertStringContainsString('hybride conditionnelle', (string) $verdict->reason);
    }

    #[Test]
    public function hybride_essence_wltp_61_3_ans_depasse_seuil_general_pas_exonere(): void
    {
        // 61 > 60 → régime général dépassé.
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 61,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 4));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_wltp_120_recent_donne_exoneration_regime_amenage(): void
    {
        // < 3 ans → régime aménagé : seuil WLTP relevé à 120.
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 120,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 1));

        self::assertTrue($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_wltp_121_recent_depasse_seuil_amenage(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 121,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 1));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function hybride_diesel_n_est_pas_eligible(): void
    {
        // Sous-jacent diesel → exclu de la combinaison (a) éligible.
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Diesel,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 30,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 4));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_pa_3cv_3_ans_donne_exoneration(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 3,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 5));

        self::assertTrue($verdict->isExempt);
    }

    #[Test]
    public function hybride_essence_pa_4cv_3_ans_depasse_seuil_general(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'homologation_method' => HomologationMethod::Pa,
            'co2_wltp' => null,
            'co2_nedc' => null,
            'taxable_horsepower' => 4,
        ]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc, vehicleAge: 5));

        self::assertFalse($verdict->isExempt);
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
        int $vehicleAge,
    ): PipelineContext {
        // L'âge est pris au 01/01/2024 - on calibre la date de première
        // immat. d'origine sur le véhicule existant (créé par la factory
        // VFC) pour produire l'âge demandé.
        $vehicle = $vfc->vehicle ?? Vehicle::factory()->create();
        $vehicle->forceFill([
            'first_origin_registration_date' => CarbonImmutable::create(2024 - $vehicleAge, 1, 1),
        ])->save();
        $vehicle->refresh();

        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
