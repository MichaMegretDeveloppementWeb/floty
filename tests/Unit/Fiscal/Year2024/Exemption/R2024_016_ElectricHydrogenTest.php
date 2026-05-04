<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionScope;
use App\Fiscal\Year2024\Exemption\R2024_016_ElectricHydrogen;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre l'exonération CO₂ électrique / hydrogène (CIBS L. 421-124).
 *
 * Spécificités :
 * - Périmètre **CO₂ uniquement** (scope `Co2Only`). La taxe polluants
 *   reste due si le véhicule n'est pas catégorie E. En pratique, la
 *   cascade `PollutantCategory::derive()` (testée séparément dans
 *   `PollutantCategoryDeriveTest`) garantit que tout véhicule électrique /
 *   hydrogène / electric+hydrogène est en catégorie E → polluants à 0 €
 *   via R-2024-014. La règle R-2024-016 est donc l'élément du couple
 *   CIBS L. 421-124 qui annule explicitement le tarif CO₂.
 * - Tarifs annuels conservés dans le breakdown (pas de zeroing) : on
 *   continue d'afficher « ce que vous auriez payé sans exonération » à
 *   titre informatif.
 *
 * Cf. `taxes-rules/2024.md` § R-2024-016 et la note d'audit produit du
 * 2026-05-04 (D1) : la couverture du test était identifiée manquante.
 */
final class R2024_016_ElectricHydrogenTest extends TestCase
{
    use RefreshDatabase;

    private R2024_016_ElectricHydrogen $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_016_ElectricHydrogen;
    }

    #[Test]
    public function rule_code_et_taxes_concernees(): void
    {
        self::assertSame('R-2024-016', $this->rule->ruleCode());
        // **Périmètre CO₂ uniquement** — invariant clé du couple CIBS
        // L. 421-124 : si on étend un jour à `Pollutants`, la cohérence
        // avec R-2024-013 (cascade polluants) doit être ré-évaluée.
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
        self::assertSame('R-2024-016', $verdict->ruleCode);
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
        // L'hybride relève éventuellement de R-2024-017 (régime conditionnel),
        // jamais de R-2024-016 — cette règle est strictement réservée aux
        // véhicules à propulsion full-électrique ou full-hydrogène.
        $vfc = $this->makeVfc(['energy_source' => EnergySource::PluginHybrid]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function contexte_sans_vfc_n_a_pas_d_exoneration(): void
    {
        // Cas race : pipeline appelé avant la classification VFC. La
        // règle doit retourner notExempt sans lever — elle se taira et
        // une autre étape lèvera l'erreur applicative si VFC manque.
        $context = new PipelineContext(
            vehicle: Vehicle::factory()->create(),
            fiscalYear: 2024,
            daysInYear: 366,
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
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
