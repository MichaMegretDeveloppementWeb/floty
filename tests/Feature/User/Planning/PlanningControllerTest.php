<?php

declare(strict_types=1);

namespace Tests\Feature\User\Planning;

use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Company;
use App\Models\Unavailability;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlanningControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_renvoie_la_heatmap_avec_vehicules_et_companies(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        Company::factory()->create();

        $this->actingAs($user)
            ->get('/app/planning')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Planning/Index/Index')
                ->has('vehicles', 1)
                ->has('companies', 1),
            );
    }

    #[Test]
    public function week_renvoie_le_detail_pour_un_couple_vehicule_semaine(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->getJson('/app/planning/week?vehicleId='.$vehicle->id.'&week=10')
            ->assertOk()
            ->assertJsonStructure([
                'weekNumber',
                'weekStart',
                'weekEnd',
                'vehicleId',
                'licensePlate',
                'days',
                'companiesOnWeek',
                'hasUnavailability',
            ]);
    }

    #[Test]
    public function index_expose_weeks_with_unavailability_pour_chaque_vehicule(): void
    {
        // ADR-0019 D5 — la heatmap doit savoir, pour chaque véhicule,
        // sur quelles semaines une indispo (tous types confondus) existe
        // pour rendre la bordure rouge côté UI.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $year = (int) config('floty.fiscal.available_years')[0];

        // Indispo en semaine ISO connue : 5 mars (`Y-03-05`) → semaine
        // ISO calculée précisément à partir du calendrier réel.
        $start = sprintf('%d-03-05', $year);
        $end = sprintf('%d-03-09', $year);
        $expectedWeek = (int) Carbon::parse($start)->isoWeek;

        Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::PoundPublic,
            'has_fiscal_impact' => true,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->actingAs($user)
            ->get('/app/planning')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Planning/Index/Index')
                ->has('vehicles', 1)
                ->where('vehicles.0.weeksWithUnavailability', [$expectedWeek]),
            );
    }

    #[Test]
    public function week_expose_has_unavailability_a_true_si_la_semaine_porte_une_indispo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $year = (int) config('floty.fiscal.available_years')[0];
        $start = sprintf('%d-03-05', $year);
        $end = sprintf('%d-03-09', $year);
        $weekNumber = (int) Carbon::parse($start)->isoWeek;

        Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week={$weekNumber}")
            ->assertOk()
            ->assertJson(['hasUnavailability' => true]);
    }

    #[Test]
    public function week_expose_has_unavailability_a_false_si_aucune_indispo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week=15")
            ->assertOk()
            ->assertJson(['hasUnavailability' => false]);
    }

    #[Test]
    public function preview_taxes_renvoie_le_breakdown(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $year = (int) config('floty.fiscal.available_years')[0];

        $this->actingAs($user)
            ->postJson('/app/planning/preview-taxes', [
                'vehicleId' => $vehicle->id,
                'companyId' => $company->id,
                'dates' => ["{$year}-03-12", "{$year}-03-13"],
            ])
            ->assertOk()
            ->assertJsonStructure([
                'fiscalYear',
                'newDaysCount',
                'existingCumul',
                'futureCumul',
                'after' => ['totalDue', 'co2Due', 'pollutantsDue', 'co2Method'],
                'incrementalDue',
            ]);
    }

    #[Test]
    public function store_bulk_cree_un_contrat_sur_la_plage_demandee(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $year = (int) config('floty.fiscal.available_years')[0];

        $this->actingAs($user)
            ->postJson('/app/planning/contracts', [
                'vehicle_ids' => [$vehicle->id],
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => "{$year}-04-10",
                'end_date' => "{$year}-04-12",
                'contract_reference' => null,
                'contract_type' => 'lcd',
                'notes' => null,
            ])
            ->assertOk()
            ->assertJsonStructure(['createdIds']);

        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseHas('contracts', [
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => "{$year}-04-10",
            'end_date' => "{$year}-04-12",
            'contract_type' => 'lcd',
        ]);
    }
}
