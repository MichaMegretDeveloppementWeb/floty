<?php

declare(strict_types=1);

namespace Tests\Feature\User\Planning;

use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Company;
use App\Models\Contract;
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
                'days' => [
                    '*' => ['date', 'dayLabel', 'contract', 'hasUnavailability'],
                ],
                'companiesOnWeek',
                'vehicleBusyDates',
            ]);
    }

    #[Test]
    public function week_expose_vehicle_busy_dates_inclut_les_contrats_hors_semaine_affichee(): void
    {
        // Régression : le drawer doit griser dans le DateRangePicker
        // les jours déjà occupés par un contrat existant, même quand
        // ceux-ci tombent dans une autre semaine que celle affichée.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $year = (int) config('floty.fiscal.available_years')[0];

        // Contrat janvier (semaines ISO ~1-2)
        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => sprintf('%d-01-01', $year),
            'end_date' => sprintf('%d-01-12', $year),
        ]);

        // On ouvre le drawer sur une semaine d'août — bien hors janvier
        $augustWeek = (int) Carbon::parse(sprintf('%d-08-12', $year))->isoWeek;

        $response = $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week={$augustWeek}")
            ->assertOk();

        $busy = $response->json('vehicleBusyDates');

        // Toutes les dates janvier 1-12 doivent figurer.
        $this->assertContains(sprintf('%d-01-01', $year), $busy);
        $this->assertContains(sprintf('%d-01-05', $year), $busy);
        $this->assertContains(sprintf('%d-01-12', $year), $busy);
        // Et pas une date hors contrat
        $this->assertNotContains(sprintf('%d-02-15', $year), $busy);
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
    public function week_expose_has_unavailability_par_jour_couvert_par_une_indispo(): void
    {
        // ADR-0019 D5 — la grille « État de la semaine » du drawer
        // applique une bordure rouge sur les seuls jours portant une
        // indispo, pas sur toute la semaine. Le DTO doit donc remonter
        // un flag par jour, pas par semaine.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $year = (int) config('floty.fiscal.available_years')[0];
        // Indispo de 2 jours en milieu de semaine — les autres jours
        // de la semaine doivent rester sans flag.
        $start = sprintf('%d-03-05', $year);
        $end = sprintf('%d-03-06', $year);
        $weekNumber = (int) Carbon::parse($start)->isoWeek;

        Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week={$weekNumber}")
            ->assertOk();

        $payload = $response->json();
        $byDate = collect($payload['days'])->keyBy('date');

        $this->assertTrue($byDate->get($start)['hasUnavailability'], "Le jour $start doit porter le flag.");
        $this->assertTrue($byDate->get($end)['hasUnavailability'], "Le jour $end doit porter le flag.");
        // Les autres jours de la semaine (lundi/mardi avant, jeudi-dimanche après)
        // doivent rester sans flag.
        foreach ($payload['days'] as $day) {
            if ($day['date'] !== $start && $day['date'] !== $end) {
                $this->assertFalse($day['hasUnavailability'], "Le jour {$day['date']} ne doit pas porter le flag.");
            }
        }
    }

    #[Test]
    public function week_expose_has_unavailability_a_false_partout_si_aucune_indispo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $response = $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week=15")
            ->assertOk();

        foreach ($response->json('days') as $day) {
            $this->assertFalse($day['hasUnavailability']);
        }
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
