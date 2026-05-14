<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Transversal;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\Year2025\Transversal\R2025_002_DailyProrata;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Goldens R-2025-002 · Prorata journalier (dénominateur 365 en 2025).
 *
 * Sémantique ADR-0014 ·
 *   numérateur = jours contractuels du couple
 *              − Σ verdicts.exemptDaysCount (R-2025-021 LCD + R-2025-008 indispos)
 *   prorata = tarif annuel plein × numérateur / 365
 *
 * Reconduction de R-2024-002 avec dénominateur 365 au lieu de 366 (2025
 * n'est pas bissextile). Couverture par cas représentatifs des bordures
 * (full year, partial, à la journée, cumul exemptions, dénominateur zéro).
 */
final class R2025_002_DailyProrataTest extends TestCase
{
    use RefreshDatabase;

    private R2025_002_DailyProrata $rule;

    private Vehicle $vehicle;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2025_002_DailyProrata;
        $this->vehicle = Vehicle::factory()->create();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function contrat_full_year_2025_donne_365_sur_365(): void
    {
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-01-01', '2025-12-31')],
            verdicts: [],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertSame(365, $result->cumulativeDaysForPair);
        self::assertSame(365, $result->daysAssignedToCompany);
        self::assertEqualsWithDelta(293.0, $result->co2Due, 0.01);
        self::assertEqualsWithDelta(100.0, $result->pollutantsDue, 0.01);
    }

    #[Test]
    public function contrat_6_mois_donne_184_sur_365(): void
    {
        // 184 jours · 01/07 → 31/12 inclusif
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-07-01', '2025-12-31')],
            verdicts: [],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertSame(184, $result->cumulativeDaysForPair);
        self::assertSame(184, $result->daysAssignedToCompany);
        self::assertEqualsWithDelta(293.0 * 184 / 365, $result->co2Due, 0.01);
        self::assertEqualsWithDelta(100.0 * 184 / 365, $result->pollutantsDue, 0.01);
    }

    #[Test]
    public function contrat_un_jour_donne_1_sur_365(): void
    {
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-06-15', '2025-06-15')],
            verdicts: [],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertSame(1, $result->cumulativeDaysForPair);
        self::assertEqualsWithDelta(293.0 / 365, $result->co2Due, 0.0001);
    }

    #[Test]
    public function full_year_avec_lcd_30j_donne_335_sur_365(): void
    {
        // Le verdict R-2025-021 retire 30j du numérateur (LCD reste
        // dans la liste de contrats du couple).
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-01-01', '2025-12-31')],
            verdicts: [ExemptionVerdict::partialDays(30, 'LCD test', 'R-2025-021')],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        // cumulativeDays = 365 (tous les jours contractuels), assigned = 335
        self::assertSame(365, $result->cumulativeDaysForPair);
        self::assertSame(335, $result->daysAssignedToCompany);
        self::assertEqualsWithDelta(293.0 * 335 / 365, $result->co2Due, 0.01);
    }

    #[Test]
    public function full_year_avec_lcd_30_et_indispo_15_jours_cumule_les_2_verdicts(): void
    {
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-01-01', '2025-12-31')],
            verdicts: [
                ExemptionVerdict::partialDays(30, 'LCD', 'R-2025-021'),
                ExemptionVerdict::partialDays(15, 'Fourrière', 'R-2025-008'),
            ],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertSame(320, $result->daysAssignedToCompany); // 365 − 30 − 15
        self::assertEqualsWithDelta(293.0 * 320 / 365, $result->co2Due, 0.01);
    }

    #[Test]
    public function verdict_qui_depasse_le_total_clamp_a_zero_pas_de_negatif(): void
    {
        // 31j contrat + 50j d'exemption → numérateur clampé à 0
        // (max(0, total − exempt)).
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-01-01', '2025-01-31')],
            verdicts: [ExemptionVerdict::partialDays(50, 'edge', 'R-2025-021')],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertSame(31, $result->cumulativeDaysForPair);
        self::assertSame(0, $result->daysAssignedToCompany);
        self::assertSame(0.0, $result->co2Due);
        self::assertSame(0.0, $result->pollutantsDue);
    }

    #[Test]
    public function aucun_contrat_donne_zero_jour_zero_taxe(): void
    {
        $context = $this->makeContext(
            contracts: [],
            verdicts: [],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertSame(0, $result->cumulativeDaysForPair);
        self::assertSame(0, $result->daysAssignedToCompany);
        self::assertSame(0.0, $result->co2Due);
        self::assertSame(0.0, $result->pollutantsDue);
    }

    #[Test]
    public function full_year_tariff_null_donne_zero_taxe(): void
    {
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-01-01', '2025-12-31')],
            verdicts: [],
            co2FullYear: null,
            pollutantsFullYear: null,
        );

        $result = $this->rule->apply($context);

        self::assertSame(365, $result->daysAssignedToCompany);
        self::assertSame(0.0, $result->co2Due);
        self::assertSame(0.0, $result->pollutantsDue);
    }

    #[Test]
    public function deux_contrats_disjoints_somment_correctement_leurs_jours(): void
    {
        // Deux contrats successifs · le total = somme exacte (pas de
        // chevauchement possible par contrainte BDD `contracts: overlapping
        // period for this vehicle`).
        $context = $this->makeContext(
            contracts: [
                $this->makeContract('2025-01-01', '2025-01-20'), // 20j
                $this->makeContract('2025-02-01', '2025-02-17'), // 17j
            ],
            verdicts: [],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        // 20 + 17 = 37 jours uniques
        self::assertSame(37, $result->cumulativeDaysForPair);
        self::assertEqualsWithDelta(293.0 * 37 / 365, $result->co2Due, 0.01);
    }

    #[Test]
    public function apply_ajoute_le_rule_code_dans_applied_rules(): void
    {
        $context = $this->makeContext(
            contracts: [$this->makeContract('2025-01-01', '2025-12-31')],
            verdicts: [],
            co2FullYear: 293.0,
            pollutantsFullYear: 100.0,
        );

        $result = $this->rule->apply($context);

        self::assertContains('R-2025-002', $result->appliedRuleCodes);
    }

    /**
     * @param  list<Contract>  $contracts
     * @param  list<ExemptionVerdict>  $verdicts
     */
    private function makeContext(
        array $contracts,
        array $verdicts,
        ?float $co2FullYear,
        ?float $pollutantsFullYear,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $this->vehicle,
            fiscalYear: 2025,
            daysInYear: 365,
            contractsForPair: $contracts,
            co2FullYearTariff: $co2FullYear,
            pollutantsFullYearTariff: $pollutantsFullYear,
            exemptionVerdicts: $verdicts,
        );
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
}
