<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Exemption;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Exemption\R2025_008_ReductiveUnavailability;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les 10 cas-tests permanents d'ADR-0016 § 7 rev. 1.1
 * (règle « indispos fiscalement réductrices »).
 */
final class R2025_008_ReductiveUnavailabilityTest extends TestCase
{
    use RefreshDatabase;

    private R2025_008_ReductiveUnavailability $rule;

    private Vehicle $vehicle;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2025_008_ReductiveUnavailability(
            new R2025_021_ShortTermRental,
        );
        $this->vehicle = Vehicle::factory()->create();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function cas_1_bofip_p190_fourriere_publique_15_jours_reduit_de_15(): void
    {
        $vehicleEvent = VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2022-06-01',
            'end_date' => '2022-06-15',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2022,
            contracts: [$this->makeFullYearContract(2022)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertTrue($verdict->isExempt);
        self::assertSame(15, $verdict->exemptDaysCount);
    }

    #[Test]
    public function cas_2_maintenance_5_jours_n_reduit_pas(): void
    {
        $vehicleEvent = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-05',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function cas_3_sinistre_reparation_simple_n_reduit_pas(): void
    {
        $vehicleEvent = VehicleEvent::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Réparation après sinistre',
            'has_fiscal_impact' => false,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-10',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function cas_4_interdiction_circulation_post_sinistre_30_jours_reduit_de_30(): void
    {
        $vehicleEvent = VehicleEvent::factory()->accidentNoCirculation()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-30',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertTrue($verdict->isExempt);
        self::assertSame(30, $verdict->exemptDaysCount);
    }

    #[Test]
    public function cas_5_suspension_ci_59_jours_reduit_de_59_en_2025_non_bissextile(): void
    {
        // Différence cruciale 2024/2025 : l'année 2025 n'est pas bissextile,
        // donc une suspension du 01/02 au 31/03 = 59 jours (28 + 31), alors
        // qu'en 2024 ce serait 60 jours (29 + 31).
        $vehicleEvent = VehicleEvent::factory()->ciSuspension()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-02-01',
            'end_date' => '2025-03-31',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertTrue($verdict->isExempt);
        self::assertSame(59, $verdict->exemptDaysCount);
    }

    #[Test]
    public function cas_6_fourriere_privee_n_reduit_pas(): void
    {
        $vehicleEvent = VehicleEvent::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Fourrière privée',
            'has_fiscal_impact' => false,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-05',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function cas_7_indispo_chevauchant_2_annees_compte_seulement_jours_dans_annee(): void
    {
        $vehicleEvent = VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-12-20',
            'end_date' => '2025-01-10',
        ]);

        $verdict2024 = $this->rule->evaluate($this->makeContext(
            year: 2024,
            contracts: [$this->makeFullYearContract(2024)],
            vehicleEvents: [$vehicleEvent],
        ));
        $verdict2025 = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        // 12 jours en 2024 (20→31 décembre inclusif), 10 jours en 2025 (1→10 janvier).
        self::assertSame(12, $verdict2024->exemptDaysCount);
        self::assertSame(10, $verdict2025->exemptDaysCount);
    }

    #[Test]
    public function cas_8_indispo_hors_jours_de_contrat_taxable_n_a_pas_d_effet(): void
    {
        $vehicleEvent = VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-09-05',
        ]);

        // Contrat couvrant uniquement Janvier : l'indispo de Septembre
        // ne croise aucun jour taxable du couple.
        $contract = Contract::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$contract],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function cas_9_cumul_reductif_et_non_reductif_seul_le_reducteur_compte(): void
    {
        $reductive = VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-10',
        ]);
        $nonReductive = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-20',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$reductive, $nonReductive],
        ));

        self::assertSame(10, $verdict->exemptDaysCount);
    }

    #[Test]
    public function cas_10_vol_simple_n_reduit_pas(): void
    {
        $vehicleEvent = VehicleEvent::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Vol du véhicule',
            'has_fiscal_impact' => false,
            'start_date' => '2025-07-01',
            'end_date' => '2025-08-14',
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$this->makeFullYearContract(2025)],
            vehicleEvents: [$vehicleEvent],
        ));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function indispo_pendant_un_lcd_ne_double_pas_le_decompte(): void
    {
        // Garde-fou : R-2025-021 retire déjà les jours de LCD du
        // numérateur. Si R-2025-008 les recomptait, on aurait un
        // double-décompte. Vérifier qu'un jour d'indispo réductrice
        // pendant un LCD ne génère aucun verdict réducteur ici.
        $reductive = VehicleEvent::factory()->poundPublic()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-04-05',
            'end_date' => '2025-04-08',
        ]);
        $lcd = Contract::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-15', // 15 j → LCD
        ]);

        $verdict = $this->rule->evaluate($this->makeContext(
            year: 2025,
            contracts: [$lcd],
            vehicleEvents: [$reductive],
        ));

        self::assertFalse($verdict->isExempt);
    }

    /**
     * @param  list<Contract>  $contracts
     * @param  list<VehicleEvent>  $vehicleEvents
     */
    private function makeContext(int $year, array $contracts, array $vehicleEvents): PipelineContext
    {
        return new PipelineContext(
            vehicle: $this->vehicle,
            fiscalYear: $year,
            daysInYear: $year % 4 === 0 ? 366 : 365,
            contractsForPair: $contracts,
            vehicleUnavailabilitiesInYear: $vehicleEvents,
        );
    }

    private function makeFullYearContract(int $year): Contract
    {
        return Contract::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => sprintf('%d-01-01', $year),
            'end_date' => sprintf('%d-12-31', $year),
        ]);
    }
}
