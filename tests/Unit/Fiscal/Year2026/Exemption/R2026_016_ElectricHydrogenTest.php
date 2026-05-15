<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionScope;
use App\Fiscal\Year2026\Exemption\R2026_016_ElectricHydrogen;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre l'exonération CO₂ électrique / hydrogène 2026 (CIBS L. 421-124
 * stable). Reconduction stricte de R-2025-016. Périmètre CO₂ uniquement
 * (scope Co2Only) · tarifs conservés (pas de zeroing).
 */
final class R2026_016_ElectricHydrogenTest extends TestCase
{
    use RefreshDatabase;

    private R2026_016_ElectricHydrogen $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2026_016_ElectricHydrogen;
    }

    #[Test]
    public function rule_code_et_taxes_concernees(): void
    {
        self::assertSame('R-2026-016', $this->rule->ruleCode());
        self::assertSame([TaxType::Co2], $this->rule->taxesConcerned());
    }

    #[Test]
    public function vehicule_strictement_electrique_donne_exoneration_co2_uniquement(): void
    {
        $vfc = $this->makeVfc(['energy_source' => EnergySource::Electric]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertTrue($verdict->isExempt);
        self::assertSame(ExemptionScope::Co2Only, $verdict->scope);
        self::assertFalse($verdict->zeroesFullYearTariffs, 'Les tarifs pleins doivent rester visibles dans le breakdown.');
        self::assertNull($verdict->exemptDaysCount, 'Exonération totale, pas journalière.');
        self::assertSame('R-2026-016', $verdict->ruleCode);
        self::assertStringContainsString('électrique', (string) $verdict->reason);
    }

    #[Test]
    public function vehicule_hydrogene_donne_exoneration_co2_uniquement(): void
    {
        $vfc = $this->makeVfc(['energy_source' => EnergySource::Hydrogen]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertTrue($verdict->isExempt);
        self::assertSame(ExemptionScope::Co2Only, $verdict->scope);
    }

    #[Test]
    public function vehicule_electrique_hydrogene_donne_exoneration_co2_uniquement(): void
    {
        $vfc = $this->makeVfc(['energy_source' => EnergySource::ElectricHydrogen]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertTrue($verdict->isExempt);
        self::assertSame(ExemptionScope::Co2Only, $verdict->scope);
    }

    #[Test]
    public function vehicule_diesel_n_a_pas_d_exoneration(): void
    {
        $vfc = $this->makeVfc(['energy_source' => EnergySource::Diesel]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertFalse($verdict->isExempt);
        self::assertNull($verdict->scope);
        self::assertNull($verdict->ruleCode);
    }

    #[Test]
    public function vehicule_essence_n_a_pas_d_exoneration(): void
    {
        $vfc = $this->makeVfc(['energy_source' => EnergySource::Gasoline]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function vehicule_hybride_rechargeable_n_a_pas_d_exoneration_via_cette_regle(): void
    {
        $vfc = $this->makeVfc(['energy_source' => EnergySource::PluginHybrid]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function contexte_sans_vfc_n_a_pas_d_exoneration(): void
    {
        $context = new PipelineContext(
            vehicle: Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: null,
        );

        $verdict = $this->rule->evaluate($context);

        self::assertFalse($verdict->isExempt);
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
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
