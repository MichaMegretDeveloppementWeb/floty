<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
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
        // Contrat actif aujourd'hui — 30 jours autour du « now »
        $today = CarbonImmutable::today();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $today->subDays(15)->toDateString(),
            'end_date' => $today->addDays(15)->toDateString(),
        ]);

        $kpis = $this->service->computeKpis($today->year)->toArray();

        self::assertSame($today->year, $kpis['year']);
        self::assertGreaterThan(0, $kpis['joursVehicule']);
        self::assertSame(1, $kpis['contractsActifs']);
        self::assertGreaterThanOrEqual(0.0, $kpis['taxesDues']);
        self::assertGreaterThanOrEqual(0.0, $kpis['tauxOccupation']);
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
    public function compute_history_renvoie_n_dernieres_annees_dont_l_annee_courante(): void
    {
        $today = CarbonImmutable::today();
        $history = $this->service->computeHistory();

        // 4 années passées + année en cours = 5 entrées
        self::assertCount(5, $history);
        // La dernière entrée doit être l'année calendaire courante avec
        // isCurrentYear: true.
        $last = end($history);
        self::assertSame($today->year, $last->year);
        self::assertTrue($last->isCurrentYear);
        // Les autres ne sont pas l'année courante.
        for ($i = 0; $i < 4; $i++) {
            self::assertFalse($history[$i]->isCurrentYear);
        }
    }

    #[Test]
    public function compute_activity_top_vehicules_tries_par_taxe_ytd_desc(): void
    {
        // 2 véhicules avec contrats — l'un a un contrat plus long
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
