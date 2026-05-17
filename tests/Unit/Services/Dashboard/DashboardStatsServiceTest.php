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
 * Tests des méthodes du `DashboardStatsService` · Présent (KPIs +
 * comparaison Y-1), Évolution (history), Tâches en attente.
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
    public function compute_kpis_fiscal_renvoie_les_4_kpis_pivots_pour_l_annee_demandee(): void
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

        $kpis = $this->service->computeKpisFiscal($today->year)->toArray();

        self::assertSame($today->year, $kpis['year']);
        self::assertGreaterThan(0, $kpis['joursVehicule']);
        self::assertSame(1, $kpis['contracts']);
        self::assertSame(1, $kpis['contractsActiveNow']);
        self::assertGreaterThanOrEqual(0.0, $kpis['taxesDues']);
        self::assertGreaterThanOrEqual(0.0, $kpis['tauxOccupation']);
        // Recettes locatives sont dans un DTO séparé (chargement defer
        // indépendant), pas dans le DTO fiscal.
        self::assertArrayNotHasKey('recettesLocativesCents', $kpis);
    }

    #[Test]
    public function compute_kpis_recettes_full_year_somme_companies(): void
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

        // Contrat 10 jours en mars.
        $marchStart = CarbonImmutable::create($year, 3, 1);
        Contract::factory()->forVehicle($vehicleA)->forCompany($companyA)->create([
            'start_date' => $marchStart->toDateString(),
            'end_date' => $marchStart->addDays(9)->toDateString(),
        ]);
        Contract::factory()->forVehicle($vehicleB)->forCompany($companyB)->create([
            'start_date' => $marchStart->toDateString(),
            'end_date' => $marchStart->addDays(9)->toDateString(),
        ]);

        $recettes = $this->service->computeKpisRecettes($year);

        // Chaque contrat = 10 jours, OptimalRateBreakdown choisit
        // 1 semaine (50 000) + 3 jours (27 000) = 77 000 cts.
        // 2 contrats × 2 entreprises = 154 000 cts.
        self::assertSame(154_000, $recettes->recettesLocativesCents);
        self::assertSame($year, $recettes->year);
    }

    #[Test]
    public function compute_kpis_fiscal_ne_porte_plus_de_comparaison_y_1(): void
    {
        // v3 · `previousYearComparison` retiré du DTO · le pipeline
        // fiscal ne tourne plus que sur 1 année au mount Dashboard
        // (l'historique multi-années chargé à la demande sert de
        // support temporel).
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        $today = CarbonImmutable::today();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $today->subDays(5)->toDateString(),
            'end_date' => $today->addDays(5)->toDateString(),
        ]);

        $kpis = $this->service->computeKpisFiscal($today->year)->toArray();

        self::assertArrayNotHasKey('previousYearComparison', $kpis);
    }

    #[Test]
    public function compute_history_jours_vehicule_se_borne_au_scope_dynamique(): void
    {
        // v4 · computeHistory split en 4 méthodes par dimension.
        // joursVehicule est l'onglet par défaut (cheap, sert au mount).
        // Sans contrat, scope = [currentYear] → 1 entrée à value=0.
        $today = CarbonImmutable::today();
        $points = $this->service->computeHistoryJoursVehicule();

        self::assertNotEmpty($points);
        $last = end($points);
        self::assertSame($today->year, $last->year);
        self::assertTrue($last->isCurrentYear);
        self::assertSame(0, $last->value);
        foreach ($points as $point) {
            self::assertGreaterThanOrEqual($today->year, $point->year);
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
        $this->service->computeKpisFiscal(2025);
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
    public function compute_history_jours_vehicule_charge_en_1_pivot_range(): void
    {
        // v4 · l'onglet `joursVehicule` (chargé au mount avec les KPIs)
        // ne fait qu'1 pivot range query couvrant toutes les années du
        // scope · pas de pipeline fiscal, pas de vehicles, pas d'indispos.
        // Garde-fou perf · count constant qq soit le nombre d'années.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        foreach (range(2021, 2024) as $year) {
            Contract::factory()->create([
                'company_id' => $company->id,
                'vehicle_id' => $vehicle->id,
                'start_date' => "{$year}-06-15",
                'end_date' => "{$year}-06-30",
            ]);
        }

        DB::enableQueryLog();
        $this->service->computeHistoryJoursVehicule();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $pivotQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'from `contracts`')
                && str_contains($q['query'], 'order by `vehicle_id` asc, `start_date` asc')
                && ! str_contains($q['query'], '`company_id`'),
        );

        self::assertCount(
            1,
            $pivotQueries,
            'Expected exactly 1 SELECT pivot range query for joursVehicule (dimension cheap), got '.count($pivotQueries),
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
        self::assertSame(0, $tasks->pendingInvoicesMonthlyTotal);
        self::assertSame([], $tasks->pendingInvoices);
    }
}
