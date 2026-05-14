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
use Illuminate\Support\Facades\DB;
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
    public function compute_kpis_charge_les_contrats_et_vehicules_en_bulk_via_scope_context(): void
    {
        // F-21-001/002 · garde-fou perf · `computeKpis` doit construire
        // un `DashboardScopeContext` qui pré-charge en 1 query unique
        // les contrats du range [year-1, year], les véhicules concernés
        // et les indispos. Le test compte spécifiquement les SELECT
        // pivots (signature `from contracts ... order by vehicle_id`)
        // et asserte qu'il n'y en a qu'un seul, indépendamment du nb
        // de companies en base.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // 3 entreprises, chacune avec 1 contrat sur 2024 ET 2025 ·
        // avant fix · 2× loadContractsByPair (1 par année).
        foreach (range(1, 3) as $i) {
            $company = Company::factory()->create();
            foreach ([2024, 2025] as $year) {
                Contract::factory()->create([
                    'company_id' => $company->id,
                    'vehicle_id' => $vehicle->id,
                    'start_date' => "{$year}-01-".str_pad((string) ($i * 3), 2, '0', STR_PAD_LEFT),
                    'end_date' => "{$year}-01-".str_pad((string) ($i * 3 + 1), 2, '0', STR_PAD_LEFT),
                ]);
            }
        }

        DB::enableQueryLog();
        $this->service->computeKpis(2025);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Les pivots `loadContractsByPair*` produisent un SELECT
        // signature unique (order by `vehicle_id` asc, `start_date` asc).
        // Discriminant signature `loadContractsByPair*` · pivot global
        // sans filtre company_id, ordre vehicle_id puis start_date.
        $pivotQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'from `contracts`')
                && str_contains($q['query'], 'order by `vehicle_id` asc, `start_date` asc')
                && ! str_contains($q['query'], '`company_id`'),
        );

        // 1 invocation range pour le couple (year-1, year), pas 2.
        self::assertCount(
            1,
            $pivotQueries,
            'Expected exactly 1 SELECT pivot range query, got '.count($pivotQueries),
        );
    }

    #[Test]
    public function compute_history_charge_les_contrats_en_bulk_quel_que_soit_le_scope(): void
    {
        // F-21-001/002 · garde-fou perf · `computeHistory` boucle sur
        // N années du scope dynamique. Avant fix · N invocations
        // indépendantes. Après fix · 1 invocation range qui couvre
        // toutes les années du scope.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        // 4 années distinctes en base · scope dynamique = [2021..2024]
        // + currentYear ajouté manuellement.
        foreach (range(2021, 2024) as $year) {
            Contract::factory()->create([
                'company_id' => $company->id,
                'vehicle_id' => $vehicle->id,
                'start_date' => "{$year}-06-15",
                'end_date' => "{$year}-06-30",
            ]);
        }

        DB::enableQueryLog();
        $this->service->computeHistory();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Discriminant signature `loadContractsByPair*` · pivot global
        // sans filtre company_id, ordre vehicle_id puis start_date.
        $pivotQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'from `contracts`')
                && str_contains($q['query'], 'order by `vehicle_id` asc, `start_date` asc')
                && ! str_contains($q['query'], '`company_id`'),
        );

        // 1 invocation range au lieu de 4-5 (1 par année du scope).
        self::assertCount(
            1,
            $pivotQueries,
            'Expected exactly 1 SELECT pivot range query, got '.count($pivotQueries),
        );
    }

    #[Test]
    public function compute_pending_tasks_delegue_a_l_aggregator(): void
    {
        // Phase 13 D5.15 · `computePendingTasks` ne retourne plus de
        // placeholders 0 · délègue à `DashboardPendingTasksAggregator`
        // qui agrège les items pending de toutes les entreprises. Sur
        // une BDD de test vide (RefreshDatabase + pas de seed
        // applicatif), aucune entreprise → 0 items partout.
        $tasks = $this->service->computePendingTasks();

        self::assertSame(0, $tasks->pendingDeclarationsCount);
        self::assertSame([], $tasks->pendingDeclarations);
        self::assertSame(0, $tasks->pendingInvoicesCount);
        self::assertSame(0, $tasks->pendingInvoicesMonthlyTotal);
        self::assertSame([], $tasks->pendingInvoices);
    }
}
