<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assignment;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use App\Services\Assignment\AssignmentQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de la composition / transformation côté service. Chaque test
 * passe par une vraie BDD pour valider l'intégration repo → service.
 */
final class AssignmentQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentQueryService $service;

    private const int YEAR = 2024;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AssignmentQueryService::class);
    }

    #[Test]
    public function load_annual_cumul_compose_un_dto_indexe_par_couple(): void
    {
        $vehicle1 = Vehicle::factory()->create();
        $vehicle2 = Vehicle::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle1->id, 'company_id' => $companyA->id, 'date' => '2024-01-01',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle1->id, 'company_id' => $companyA->id, 'date' => '2024-01-02',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle1->id, 'company_id' => $companyB->id, 'date' => '2024-02-01',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle2->id, 'company_id' => $companyA->id, 'date' => '2024-03-01',
        ]);

        $cumul = $this->service->loadAnnualCumul(self::YEAR);

        self::assertSame(2, $cumul->forPair($vehicle1->id, $companyA->id));
        self::assertSame(1, $cumul->forPair($vehicle1->id, $companyB->id));
        self::assertSame(1, $cumul->forPair($vehicle2->id, $companyA->id));
        self::assertSame(0, $cumul->forPair($vehicle2->id, $companyB->id));
    }

    #[Test]
    public function load_week_density_calcule_le_nombre_de_jours_par_semaine_iso(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        // 2024-01-01 = lundi, semaine ISO 1
        // 2024-01-08 = lundi, semaine ISO 2
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id, 'date' => '2024-01-01',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id, 'date' => '2024-01-02',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id, 'date' => '2024-01-08',
        ]);

        $density = $this->service->loadWeekDensity(self::YEAR);

        self::assertSame(2, $density["{$vehicle->id}|1"]);
        self::assertSame(1, $density["{$vehicle->id}|2"]);
    }

    #[Test]
    public function find_vehicle_dates_compose_le_dto_avec_dates_uniques_et_par_company(): void
    {
        $vehicle = Vehicle::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyA->id, 'date' => '2024-01-01',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyB->id, 'date' => '2024-01-02',
        ]);

        $data = $this->service->findVehicleDates($vehicle->id, self::YEAR);

        self::assertEqualsCanonicalizing(['2024-01-01', '2024-01-02'], $data->vehicleBusyDates);
        self::assertEqualsCanonicalizing(['2024-01-01'], $data->pairDates[(string) $companyA->id]);
        self::assertEqualsCanonicalizing(['2024-01-02'], $data->pairDates[(string) $companyB->id]);
    }

    #[Test]
    public function find_dates_for_pair_retourne_des_strings_iso(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id, 'date' => '2024-04-15',
        ]);

        $dates = $this->service->findDatesForPair($vehicle->id, $company->id, self::YEAR);

        self::assertSame(['2024-04-15'], $dates);
    }

    #[Test]
    public function load_vehicle_weekly_breakdown_compte_les_jours_par_semaine_iso_et_company(): void
    {
        $vehicle = Vehicle::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Semaine ISO 1 (2024-01-01 = lundi) :
        //   - 2 jours pour A (lun + mar)
        //   - 1 jour pour B (mer)
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyA->id, 'date' => '2024-01-01',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyA->id, 'date' => '2024-01-02',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyB->id, 'date' => '2024-01-03',
        ]);
        // Semaine ISO 2 (2024-01-08 = lundi) :
        //   - 2 jours pour B (lun + mar)
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyB->id, 'date' => '2024-01-08',
        ]);
        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $companyB->id, 'date' => '2024-01-09',
        ]);

        $breakdown = $this->service->loadVehicleWeeklyBreakdown($vehicle->id, self::YEAR);

        self::assertSame(2, $breakdown[1][$companyA->id]);
        self::assertSame(1, $breakdown[1][$companyB->id]);
        self::assertSame(2, $breakdown[2][$companyB->id]);
        self::assertArrayNotHasKey(3, $breakdown, 'Aucune attribution semaine 3 → entrée absente.');
    }
}
