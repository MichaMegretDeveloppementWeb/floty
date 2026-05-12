<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Models\VehicleYearlyPricing;
use App\Services\Dashboard\DashboardStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests des 4 méthodes du `DashboardStatsService` refondu (chantier η
 * Phase 4) : Présent (KPIs + comparaison Y-1), Évolution (history),
 * Exploration (activity), Tâches en attente (placeholders).
 */
final class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DashboardStatsService::class);
    }

    #[Test]
    public function compute_kpis_renvoie_les_4_kpis_pivots_pour_l_annee_demandee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat actif aujourd'hui · 30 jours autour du « now »
        $today = CarbonImmutable::today();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $today->subDays(15)->toDateString(),
            'end_date' => $today->addDays(15)->toDateString(),
        ]);

        $kpis = $this->service->computeKpis($today->year)->toArray();

        self::assertSame($today->year, $kpis['year']);
        self::assertGreaterThan(0, $kpis['joursVehicule']);
        self::assertSame(1, $kpis['contracts']);
        self::assertSame(1, $kpis['contractsActiveNow']);
        self::assertGreaterThanOrEqual(0.0, $kpis['taxesDues']);
        self::assertGreaterThanOrEqual(0.0, $kpis['tauxOccupation']);
        // Recettes locatives : véhicule sans tarif annuel → mode partiel = 0.
        self::assertSame(0, $kpis['recettesLocativesCents']);
    }

    #[Test]
    public function compute_kpis_recettes_locatives_full_year_somme_companies(): void
    {
        $today = CarbonImmutable::today();
        $year = $today->year;

        // 2 entreprises, 2 véhicules avec tarif annuel renseigné, 1 contrat
        // chacun couvrant 10 jours dans l'année courante.
        $vehicleA = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicleA->id]);
        VehicleYearlyPricing::factory()->create([
            'vehicle_id' => $vehicleA->id,
            'year' => $year,
            'daily_rate_cents' => 9_000,
            'weekly_rate_cents' => 50_000,
            'monthly_rate_cents' => 180_000,
        ]);
        $vehicleB = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicleB->id]);
        VehicleYearlyPricing::factory()->create([
            'vehicle_id' => $vehicleB->id,
            'year' => $year,
            'daily_rate_cents' => 9_000,
            'weekly_rate_cents' => 50_000,
            'monthly_rate_cents' => 180_000,
        ]);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Contrat 10 jours en mars (mois passé ou futur selon today, mais
        // toujours dans l'année courante puisqu'on prend full year).
        $marchStart = CarbonImmutable::create($year, 3, 1);
        Contract::factory()->forVehicle($vehicleA)->forCompany($companyA)->create([
            'start_date' => $marchStart->toDateString(),
            'end_date' => $marchStart->addDays(9)->toDateString(),
        ]);
        Contract::factory()->forVehicle($vehicleB)->forCompany($companyB)->create([
            'start_date' => $marchStart->toDateString(),
            'end_date' => $marchStart->addDays(9)->toDateString(),
        ]);

        $kpis = $this->service->computeKpis($year)->toArray();

        // Chaque contrat = 10 jours × 9 000 cts = 90 000 cts. Mais
        // OptimalRateBreakdown choisit le combo le moins cher : 1 semaine
        // (50 000) + 3 jours (27 000) = 77 000 cts. Avec 2 contrats sur
        // 2 entreprises, total = 154 000 cts.
        self::assertSame(154_000, $kpis['recettesLocativesCents']);
    }

    #[Test]
    public function compute_kpis_renvoie_null_pour_comparaison_si_y_moins_1_vide(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        $today = CarbonImmutable::today();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $today->subDays(5)->toDateString(),
            'end_date' => $today->addDays(5)->toDateString(),
        ]);

        $kpis = $this->service->computeKpis($today->year)->toArray();

        // Aucun contrat sur Y-1 → previousYearComparison est null.
        self::assertNull($kpis['previousYearComparison']);
    }

    #[Test]
    public function compute_history_se_borne_au_scope_dynamique_des_contrats(): void
    {
        // Sans contrat, le scope du resolver = [currentYear] uniquement.
        // computeHistory garantit que l'année courante figure même si
        // scope vide → renvoie au moins 1 entrée.
        $today = CarbonImmutable::today();
        $history = $this->service->computeHistory();

        self::assertNotEmpty($history);
        $last = end($history);
        self::assertSame($today->year, $last->year);
        self::assertTrue($last->isCurrentYear);
        // Aucune année antérieure à 2024 (scope contrats vide) ne doit
        // apparaître artificiellement.
        foreach ($history as $entry) {
            self::assertGreaterThanOrEqual($today->year, $entry->year);
        }
    }

    #[Test]
    public function compute_activity_top_vehicules_tries_par_taxe_ytd_desc(): void
    {
        // 2 véhicules avec contrats · l'un a un contrat plus long
        $today = CarbonImmutable::today();
        $v1 = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v1->id]);
        $v2 = Vehicle::factory()->create(['license_plate' => 'BB-002-BB']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2->id]);
        $company = Company::factory()->create();
        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => $today->subDays(20)->toDateString(),
            'end_date' => $today->addDays(20)->toDateString(),
        ]);
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => $today->subDays(2)->toDateString(),
            'end_date' => $today->addDays(2)->toDateString(),
        ]);

        $activity = $this->service->computeActivity();

        // Top véhicules : ordre DESC par taxYearToDate
        self::assertGreaterThanOrEqual(0, count($activity->topExpensiveVehicles));
        if (count($activity->topExpensiveVehicles) >= 2) {
            self::assertGreaterThanOrEqual(
                $activity->topExpensiveVehicles[1]->taxYearToDate,
                $activity->topExpensiveVehicles[0]->taxYearToDate,
            );
        }

        // Heatmap : 30 jours par véhicule, statut 'occupied' ou 'free'.
        foreach ($activity->last30DaysHeatmap as $row) {
            self::assertCount(30, $row->days);
        }
    }

    #[Test]
    public function compute_pending_tasks_renvoie_zeros_placeholders(): void
    {
        $tasks = $this->service->computePendingTasks();

        self::assertSame(0, $tasks->pendingDeclarations);
        self::assertSame(0, $tasks->pendingInvoices);
    }
}
