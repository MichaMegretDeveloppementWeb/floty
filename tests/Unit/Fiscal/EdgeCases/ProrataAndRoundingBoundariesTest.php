<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\EdgeCases;

use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\Year2025\Pricing\R2025_010_WltpProgressive;
use App\Fiscal\Year2025\Pricing\R2025_012_PaProgressive;
use App\Fiscal\Year2025\Transversal\R2025_002_DailyProrata;
use App\Fiscal\Year2026\Pricing\R2026_010_WltpProgressive;
use App\Fiscal\Year2026\Pricing\R2026_012_PaProgressive;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Φ.2 · tests aux bordures · prorata jour-par-jour, arrondis half-up,
 * stress numérique (CO₂ et PA extrêmes), transition minuit.
 *
 * Couvre TIER A.2, A.3, B.1, B.2 du plan d'audit final.
 */
final class ProrataAndRoundingBoundariesTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vehicle = Vehicle::factory()->create();
        $this->company = Company::factory()->create();
    }

    // ============================================================
    // A.2 · Prorata jour-par-jour · chaque jour compté exactement 1 fois
    // ============================================================

    #[Test]
    public function jour_unique_du_31_decembre_2025_donne_1_sur_365(): void
    {
        // Bordure haute · dernier jour de l'année.
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-12-31', '2025-12-31');
        $context = $this->makeContext([$contract], [], 365, 365.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(1, $result->cumulativeDaysForPair);
        self::assertEqualsWithDelta(365.0 / 365, $result->co2Due, 0.0001);
    }

    #[Test]
    public function jour_unique_du_01_janvier_2025_donne_1_sur_365(): void
    {
        // Bordure basse · premier jour de l'année.
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-01-01', '2025-01-01');
        $context = $this->makeContext([$contract], [], 365, 365.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(1, $result->cumulativeDaysForPair);
    }

    #[Test]
    public function annee_complete_2025_non_bissextile_donne_365_sur_365(): void
    {
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-01-01', '2025-12-31');
        $context = $this->makeContext([$contract], [], 365, 365.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(365, $result->cumulativeDaysForPair);
        self::assertEqualsWithDelta(365.0, $result->co2Due, 0.0001);
    }

    #[Test]
    public function annee_complete_2024_bissextile_donne_366_sur_366(): void
    {
        $rule = new R2025_002_DailyProrata;
        // Test conceptuel · prorata simple avec dénominateur 366.
        $contract = $this->makeContract('2024-01-01', '2024-12-31');
        $context = $this->makeContext([$contract], [], 366, 366.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(366, $result->cumulativeDaysForPair);
        self::assertEqualsWithDelta(366.0, $result->co2Due, 0.0001);
    }

    #[Test]
    public function deux_contrats_jointifs_sans_chevauchement_somment_au_jour_pres(): void
    {
        // Test que start_date = end_date du précédent +1 jour
        // ne crée pas de double-comptage ni d'oubli.
        $rule = new R2025_002_DailyProrata;
        $contracts = [
            $this->makeContract('2025-01-01', '2025-06-30'), // 181 j
            $this->makeContract('2025-07-01', '2025-12-31'), // 184 j
        ];
        $context = $this->makeContext($contracts, [], 365, 365.0, 100.0);

        $result = $rule->apply($context);

        // 181 + 184 = 365 jours exacts (couverture complète sans gap ni chevauchement)
        self::assertSame(365, $result->cumulativeDaysForPair);
    }

    #[Test]
    public function exemption_qui_couvre_tous_les_jours_de_contrat_donne_zero(): void
    {
        // 31j contrat · 31j d'exemption → numérateur = 0
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-01-01', '2025-01-31');
        $verdicts = [ExemptionVerdict::partialDays(31, 'Test', 'R-2025-021')];
        $context = $this->makeContext([$contract], $verdicts, 365, 365.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(31, $result->cumulativeDaysForPair);
        self::assertSame(0, $result->daysAssignedToCompany);
        self::assertSame(0.0, $result->co2Due);
    }

    // ============================================================
    // A.3 · Arrondis half-up · cas limites au .5
    // ============================================================

    #[Test]
    public function pa_3cv_donne_5250_euros_exact_pas_de_perte_arrondi(): void
    {
        // 3 × 1750 = 5250 € · entier · pas de perte arrondi possible.
        $rule = new R2025_012_PaProgressive;
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Pa,
            'taxable_horsepower' => 3,
        ]);
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2025,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: HomologationMethod::Pa,
        );

        $tariff = $rule->price($context)->co2FullYearTariff;
        self::assertSame(5250.0, $tariff);
    }

    #[Test]
    public function prorata_centieme_pres_avec_arrondi_au_centime(): void
    {
        // Prorata 100j sur 365 · 100 × 100 / 365 = 27,3972...
        // Le moteur conserve la précision · arrondi half-up
        // s'applique seulement au niveau redevable final.
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-01-01', '2025-04-10'); // 100j
        $context = $this->makeContext([$contract], [], 365, 100.0, 100.0);

        $result = $rule->apply($context);

        $expected = 100.0 * 100 / 365;
        self::assertEqualsWithDelta($expected, $result->co2Due, 0.0001);
    }

    // ============================================================
    // B.1 · Stress numérique · valeurs extrêmes
    // ============================================================

    #[Test]
    public function wltp_300g_dans_la_tranche_ouverte_donne_grosse_taxe(): void
    {
        // 300 g/km · stress test très polluant · WLTP 2026.
        // Tranche ouverte 166+ à 65 €/g.
        // Calcul · 0 + 41 + 16 + 96 + 80 + 200 + 1000 + 1200 + (300-165)*65
        // = 41+16+96+80+200+1000+1200 + 8775 = 11408
        $rule = new R2026_010_WltpProgressive;
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 300,
        ]);
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: HomologationMethod::Wltp,
        );

        $tariff = $rule->price($context)->co2FullYearTariff;
        self::assertSame(11408.0, $tariff);
    }

    #[Test]
    public function pa_18cv_dans_tranche_ouverte_durci_2026(): void
    {
        // 18 CV PA 2026 · tranche ouverte 16+ à 6500 €/CV
        // Calcul · 3×2000 + 3×3000 + 4×4500 + 5×5250 + 3×6500
        // = 6000 + 9000 + 18000 + 26250 + 19500 = 78750
        $rule = new R2026_012_PaProgressive;
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Pa,
            'taxable_horsepower' => 18,
        ]);
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: HomologationMethod::Pa,
        );

        $tariff = $rule->price($context)->co2FullYearTariff;
        self::assertSame(78750.0, $tariff);
    }

    #[Test]
    public function wltp_0_donne_zero_pas_de_division_par_zero(): void
    {
        // Stress · CO₂ = 0 (électrique théorique, mais homologation_method WLTP)
        $rule = new R2025_010_WltpProgressive;
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 0,
        ]);
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2025,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: HomologationMethod::Wltp,
        );

        $tariff = $rule->price($context)->co2FullYearTariff;
        self::assertSame(0.0, $tariff);
    }

    // ============================================================
    // B.2 · Transition minuit · contrat 1 jour exact
    // ============================================================

    #[Test]
    public function contrat_un_jour_pile_compte_pour_un_jour(): void
    {
        // start_date == end_date · diffInDays = 0 + 1 (inclusion) = 1 jour
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-06-15', '2025-06-15');
        $context = $this->makeContext([$contract], [], 365, 365.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(1, $result->cumulativeDaysForPair);
        self::assertEqualsWithDelta(365.0 / 365, $result->co2Due, 0.0001);
        self::assertEqualsWithDelta(100.0 / 365, $result->pollutantsDue, 0.0001);
    }

    #[Test]
    public function contrat_du_29_au_31_decembre_compte_3_jours(): void
    {
        // Test inclusion des bornes · 29, 30, 31 décembre = 3 jours
        $rule = new R2025_002_DailyProrata;
        $contract = $this->makeContract('2025-12-29', '2025-12-31');
        $context = $this->makeContext([$contract], [], 365, 365.0, 100.0);

        $result = $rule->apply($context);

        self::assertSame(3, $result->cumulativeDaysForPair);
    }

    private function makeContract(string $start, string $end): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $this->vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => 'lld',
            'notes' => null,
        ], true);
        $contract->save();

        return $contract;
    }

    /**
     * @param  list<Contract>  $contracts
     * @param  list<ExemptionVerdict>  $verdicts
     */
    private function makeContext(
        array $contracts,
        array $verdicts,
        int $daysInYear,
        float $co2FullYear,
        float $pollutantsFullYear,
    ): PipelineContext {
        $year = (int) substr($contracts[0]->start_date->format('Y'), 0, 4);

        return new PipelineContext(
            vehicle: $this->vehicle,
            fiscalYear: $year,
            daysInYear: $daysInYear,
            contractsForPair: $contracts,
            co2FullYearTariff: $co2FullYear,
            pollutantsFullYearTariff: $pollutantsFullYear,
            exemptionVerdicts: $verdicts,
        );
    }
}
