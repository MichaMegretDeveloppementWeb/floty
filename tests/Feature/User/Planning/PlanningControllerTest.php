<?php

declare(strict_types=1);

namespace Tests\Feature\User\Planning;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
                ->has('companies', 1)
                // Coded fiscal years (registry) drive the "no fiscal rules" UI.
                ->where('fiscalSupportedYears', fn (Collection $years): bool => $years->contains(2024)
                    && $years->contains(2025)
                    && $years->contains(2026)),
            );
    }

    #[Test]
    public function index_sert_costs_en_inertia_defer_absent_du_premier_render(): void
    {
        // Chantier perf 2026-05-17 · split en 2 props defer
        // (`fullYearCosts` group "fast" + `realCosts` group "slow") ·
        // les 2 sont absentes du 1er render. Le frontend récupère via
        // auto-fetch des 2 groups en parallèle.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get('/app/planning')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Planning/Index/Index')
                ->has('vehicles', 1)
                ->missing('vehicles.0.annualTaxDue')
                ->missing('vehicles.0.fullYearTax')
                ->missing('vehicles.0.dailyTaxRate')
                ->missing('fullYearCosts')
                ->missing('realCosts'),
            );
    }

    #[Test]
    public function company_index_sert_costs_en_inertia_defer_absent_du_premier_render(): void
    {
        // Idem `index_sert_costs_en_inertia_defer_absent_du_premier_render`
        // pour la Vue Entreprise.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->get('/app/planning/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Planning/Company/Index')
                ->has('vehicles', 1)
                ->missing('vehicles.0.annualTaxDueForCompany')
                ->missing('vehicles.0.fullYearTax')
                ->missing('vehicles.0.dailyTaxRate')
                ->missing('fullYearCosts')
                ->missing('realCosts'),
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
                    '*' => ['date', 'dayLabel', 'contract', 'hasVehicleEvent'],
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

        $year = 2024;

        // Contrat janvier (semaines ISO ~1-2)
        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => sprintf('%d-01-01', $year),
            'end_date' => sprintf('%d-01-12', $year),
        ]);

        // On ouvre le drawer sur une semaine d'août - bien hors janvier
        $augustWeek = (int) Carbon::parse(sprintf('%d-08-12', $year))->isoWeek;

        $response = $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week={$augustWeek}&year={$year}")
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
        // ADR-0019 D5 - la heatmap doit savoir, pour chaque véhicule,
        // sur quelles semaines une indispo (toutes natures confondues) existe
        // pour rendre la bordure rouge côté UI.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $year = 2024;

        // Indispo en semaine ISO connue : 5 mars (`Y-03-05`) → semaine
        // ISO calculée précisément à partir du calendrier réel.
        $start = sprintf('%d-03-05', $year);
        $end = sprintf('%d-03-09', $year);
        $expectedWeek = (int) Carbon::parse($start)->isoWeek;

        VehicleEvent::factory()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Mise en fourrière',
            'has_fiscal_impact' => true,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->actingAs($user)
            ->get('/app/planning?year='.$year)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Planning/Index/Index')
                ->has('vehicles', 1)
                ->where('vehicles.0.weeksWithVehicleEvent', [$expectedWeek]),
            );
    }

    #[Test]
    public function week_expose_has_unavailability_par_jour_couvert_par_une_indispo(): void
    {
        // ADR-0019 D5 - la grille « État de la semaine » du drawer
        // applique une bordure rouge sur les seuls jours portant une
        // indispo, pas sur toute la semaine. Le DTO doit donc remonter
        // un flag par jour, pas par semaine.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $year = 2024;
        // Indispo de 2 jours en milieu de semaine - les autres jours
        // de la semaine doivent rester sans flag.
        $start = sprintf('%d-03-05', $year);
        $end = sprintf('%d-03-06', $year);
        $weekNumber = (int) Carbon::parse($start)->isoWeek;

        VehicleEvent::factory()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Entretien courant',
            'has_fiscal_impact' => false,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week={$weekNumber}&year={$year}")
            ->assertOk();

        /** @var array{days: list<array<string, mixed>>} $payload */
        $payload = $response->json();
        $byDate = collect($payload['days'])->keyBy('date');

        $this->assertTrue($byDate->get($start)['hasVehicleEvent'], "Le jour $start doit porter le flag.");
        $this->assertTrue($byDate->get($end)['hasVehicleEvent'], "Le jour $end doit porter le flag.");
        // Les autres jours de la semaine (lundi/mardi avant, jeudi-dimanche après)
        // doivent rester sans flag.
        foreach ($payload['days'] as $day) {
            if ($day['date'] !== $start && $day['date'] !== $end) {
                $this->assertFalse($day['hasVehicleEvent'], "Le jour {$day['date']} ne doit pas porter le flag.");
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
            $this->assertFalse($day['hasVehicleEvent']);
        }
    }

    #[Test]
    public function week_anonymise_les_contrats_des_autres_entreprises_avec_company_id(): void
    {
        // Vue Entreprise (chantier P3) : la route GET /app/planning/week
        // accepte un query param `companyId` optionnel. Quand fourni,
        // le payload anonymise les contrats des autres entreprises.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $year = 2024;
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $companyA->id,
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
        ]);
        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $companyB->id,
            'start_date' => $weekStart->copy()->addDays(2)->toDateString(),
            'end_date' => $weekStart->copy()->addDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/app/planning/week?vehicleId={$vehicle->id}&week=10&year={$year}&companyId={$companyA->id}")
            ->assertOk();

        $days = $response->json('days');

        // Lundi : contract companyA non-anonymisé.
        self::assertNotNull($days[0]['contract']);
        self::assertSame($companyA->id, $days[0]['contract']['company']['id']);
        self::assertFalse($days[0]['isOccupiedByOther']);

        // Mercredi : contract companyB anonymisé.
        self::assertNull($days[2]['contract']);
        self::assertTrue($days[2]['isOccupiedByOther']);

        // companiesOnWeek filtré.
        $companies = $response->json('companiesOnWeek');
        self::assertCount(1, $companies);
        self::assertSame($companyA->id, $companies[0]['company']['id']);
    }

    #[Test]
    public function company_index_renvoie_la_heatmap_filtree_pour_l_entreprise(): void
    {
        // Vue Entreprise (chantier P1) : route
        // GET /app/planning/companies/{company} renvoie le composant
        // dédié + la heatmap company-scoped.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $year = 2024;
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
        ]);

        $this->actingAs($user)
            ->get('/app/planning/companies/'.$company->id.'?year='.$year)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Planning/Company/Index')
                ->has('vehicles', 1)
                ->where('vehicles.0.weeksGlobal.9', 1)
                ->where('vehicles.0.weeksForCompany.9', 1)
                ->where('vehicles.0.daysTotalForCompany', 1)
                ->where('company.id', $company->id)
                ->has('companies', 1)
                ->where('selectedYear', $year),
            );
    }

    #[Test]
    public function preview_taxes_renvoie_le_breakdown(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        $year = 2024;

        $this->actingAs($user)
            ->postJson('/app/planning/preview-taxes', [
                'vehicleId' => $vehicle->id,
                'companyId' => $company->id,
                'dates' => ["{$year}-03-12", "{$year}-03-13"],
            ])
            ->assertOk()
            ->assertJsonStructure([
                'fiscalYear',
                'daysCount',
                'breakdown' => ['totalDue', 'co2Due', 'pollutantsDue', 'co2Method', 'appliedExemptions'],
            ]);
    }

    #[Test]
    public function preview_taxes_signale_une_annee_sans_regles_fiscales(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2020-01-01',
            'effective_to' => null,
        ]);
        $company = Company::factory()->create();

        // 2027 hors registre fiscal · réponse 200 neutre (supported=false,
        // breakdown=null), pas de 422 ni de toast d'erreur dans le wizard.
        $this->actingAs($user)
            ->postJson('/app/planning/preview-taxes', [
                'vehicleId' => $vehicle->id,
                'companyId' => $company->id,
                'dates' => ['2027-03-12', '2027-03-13'],
            ])
            ->assertOk()
            ->assertJson([
                'fiscalYear' => 2027,
                'supported' => false,
                'breakdown' => null,
            ]);
    }

    #[Test]
    public function store_bulk_cree_un_contrat_sur_la_plage_demandee(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $year = 2024;

        $this->actingAs($user)
            ->postJson('/app/planning/contracts', [
                'vehicle_ids' => [$vehicle->id],
                'company_id' => $company->id,
                'driver_ids' => [],
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
