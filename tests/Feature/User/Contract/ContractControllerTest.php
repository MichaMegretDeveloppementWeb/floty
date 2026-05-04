<?php

declare(strict_types=1);

namespace Tests\Feature\User\Contract;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\AssertsPaginatedIndex;
use Tests\TestCase;

/**
 * Tests Feature CRUD Contract - couvre l'auth, les redirects, la
 * validation FR et la propagation au repo via Action+Service.
 */
final class ContractControllerTest extends TestCase
{
    use AssertsPaginatedIndex;
    use RefreshDatabase;

    #[Test]
    public function index_renvoie_la_liste_des_contrats(): void
    {
        $user = User::factory()->create();
        Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();
        Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $this->actingAs($user)
            ->get('/app/contracts')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $page->component('User/Contracts/Index/Index');
                $this->assertPaginatedShape(
                    $page,
                    'contracts',
                    expectedDataCount: 2,
                    expectedMeta: ['total' => 2, 'currentPage' => 1, 'perPage' => 20],
                );
            });
    }

    #[Test]
    public function index_paginate_avec_per_page_personnalise(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        // 25 contrats sur le même vehicle avec dates non-overlap (1 par mois sur 25 mois)
        for ($i = 0; $i < 25; $i++) {
            $start = sprintf('2023-%02d-01', ($i % 12) + 1);
            $end = sprintf('2023-%02d-15', ($i % 12) + 1);
            // Étaler sur plusieurs years pour éviter overlap
            $year = 2023 + intdiv($i, 12);
            Contract::factory()->create([
                'vehicle_id' => Vehicle::factory()->create()->id,
                'company_id' => $company->id,
                'start_date' => sprintf('%04d-01-01', $year + $i),
                'end_date' => sprintf('%04d-01-15', $year + $i),
            ]);
        }

        $this->actingAs($user)
            ->get('/app/contracts?perPage=10')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'contracts',
                expectedDataCount: 10,
                expectedMeta: ['total' => 25, 'lastPage' => 3, 'perPage' => 10],
            ));
    }

    #[Test]
    public function index_per_page_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/contracts?perPage=33')
            ->assertSessionHasErrors(['perPage']);
    }

    #[Test]
    public function index_sort_key_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/contracts?sortKey=password')
            ->assertSessionHasErrors(['sortKey']);
    }

    #[Test]
    public function index_filtre_par_vehicle_id(): void
    {
        $user = User::factory()->create();
        $v1 = Vehicle::factory()->create();
        $v2 = Vehicle::factory()->create();
        $company = Company::factory()->create();
        Contract::factory()->create([
            'vehicle_id' => $v1->id, 'company_id' => $company->id,
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ]);
        Contract::factory()->create([
            'vehicle_id' => $v2->id, 'company_id' => $company->id,
            'start_date' => '2025-02-01', 'end_date' => '2025-02-28',
        ]);

        $this->actingAs($user)
            ->get('/app/contracts?vehicleId='.$v1->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'contracts',
                expectedDataCount: 1,
                expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_filtre_par_company_id_driver_id_et_type(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vehicle2 = Vehicle::factory()->create();
        $vehicle3 = Vehicle::factory()->create();
        $c1 = Company::factory()->create();
        $c2 = Company::factory()->create();
        $driver = Driver::factory()->create();

        // Match: c1 + driver + lcd
        Contract::factory()->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $c1->id,
            'driver_id' => $driver->id, 'contract_type' => ContractType::Lcd,
            'start_date' => '2025-01-01', 'end_date' => '2025-01-15',
        ]);
        // Pas match (c2)
        Contract::factory()->create([
            'vehicle_id' => $vehicle2->id, 'company_id' => $c2->id,
            'driver_id' => $driver->id, 'contract_type' => ContractType::Lcd,
            'start_date' => '2025-02-01', 'end_date' => '2025-02-15',
        ]);
        // Pas match (lld)
        Contract::factory()->create([
            'vehicle_id' => $vehicle3->id, 'company_id' => $c1->id,
            'driver_id' => $driver->id, 'contract_type' => ContractType::Lld,
            'start_date' => '2025-03-01', 'end_date' => '2025-12-31',
        ]);

        $this->actingAs($user)
            ->get('/app/contracts?companyId='.$c1->id.'&driverId='.$driver->id.'&type=lcd')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'contracts',
                expectedDataCount: 1,
                expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_filtre_par_periode_chevauchement(): void
    {
        $user = User::factory()->create();
        $vehicle1 = Vehicle::factory()->create();
        $vehicle2 = Vehicle::factory()->create();
        $vehicle3 = Vehicle::factory()->create();
        $company = Company::factory()->create();

        // Avant la fenêtre — pas match
        Contract::factory()->create([
            'vehicle_id' => $vehicle1->id, 'company_id' => $company->id,
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ]);
        // Chevauche fenêtre [2025-03-01, 2025-03-31] — match
        Contract::factory()->create([
            'vehicle_id' => $vehicle2->id, 'company_id' => $company->id,
            'start_date' => '2025-03-15', 'end_date' => '2025-04-15',
        ]);
        // Après la fenêtre — pas match
        Contract::factory()->create([
            'vehicle_id' => $vehicle3->id, 'company_id' => $company->id,
            'start_date' => '2025-05-01', 'end_date' => '2025-05-31',
        ]);

        $this->actingAs($user)
            ->get('/app/contracts?periodStart=2025-03-01&periodEnd=2025-03-31')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'contracts',
                expectedDataCount: 1,
                expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_search_combo_vehicle_company_driver(): void
    {
        $user = User::factory()->create();
        $vehicle1 = Vehicle::factory()->create(['license_plate' => 'AA-111-AA', 'brand' => 'Renault', 'model' => 'Clio']);
        $vehicle2 = Vehicle::factory()->create(['license_plate' => 'BB-222-BB', 'brand' => 'Peugeot', 'model' => '208']);
        $vehicle3 = Vehicle::factory()->create(['license_plate' => 'CC-333-CC', 'brand' => 'Citroën', 'model' => 'C3']);
        $companyA = Company::factory()->create(['short_code' => 'ALP', 'legal_name' => 'Alpha SARL']);
        $companyB = Company::factory()->create(['short_code' => 'BTA', 'legal_name' => 'Beta SAS']);
        $driver = Driver::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Martin']);

        Contract::factory()->create([
            'vehicle_id' => $vehicle1->id, 'company_id' => $companyA->id,
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ]);
        Contract::factory()->create([
            'vehicle_id' => $vehicle2->id, 'company_id' => $companyB->id,
            'driver_id' => $driver->id,
            'start_date' => '2025-02-01', 'end_date' => '2025-02-28',
        ]);
        Contract::factory()->create([
            'vehicle_id' => $vehicle3->id, 'company_id' => $companyA->id,
            'start_date' => '2025-03-01', 'end_date' => '2025-03-31',
        ]);

        // Search par plate
        $this->actingAs($user)
            ->get('/app/contracts?search=BB-222')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page, 'contracts', expectedDataCount: 1, expectedMeta: ['total' => 1],
            ));

        // Search par brand
        $this->actingAs($user)
            ->get('/app/contracts?search=Renault')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page, 'contracts', expectedDataCount: 1, expectedMeta: ['total' => 1],
            ));

        // Search par company short_code
        $this->actingAs($user)
            ->get('/app/contracts?search=ALP')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page, 'contracts', expectedDataCount: 2, expectedMeta: ['total' => 2],
            ));

        // Search par driver name
        $this->actingAs($user)
            ->get('/app/contracts?search=Sophie')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page, 'contracts', expectedDataCount: 1, expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_sort_par_start_date_desc(): void
    {
        $user = User::factory()->create();
        $v1 = Vehicle::factory()->create();
        $v2 = Vehicle::factory()->create();
        $v3 = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->create([
            'vehicle_id' => $v1->id, 'company_id' => $company->id,
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ]);
        Contract::factory()->create([
            'vehicle_id' => $v2->id, 'company_id' => $company->id,
            'start_date' => '2025-03-01', 'end_date' => '2025-03-31',
        ]);
        Contract::factory()->create([
            'vehicle_id' => $v3->id, 'company_id' => $company->id,
            'start_date' => '2025-02-01', 'end_date' => '2025-02-28',
        ]);

        $this->actingAs($user)
            ->get('/app/contracts?sortKey=startDate&sortDirection=desc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contracts.data.0.startDate', '2025-03-01')
                ->where('contracts.data.1.startDate', '2025-02-01')
                ->where('contracts.data.2.startDate', '2025-01-01'),
            );
    }

    #[Test]
    public function index_sort_par_duration_desc(): void
    {
        $user = User::factory()->create();
        $v1 = Vehicle::factory()->create();
        $v2 = Vehicle::factory()->create();
        $company = Company::factory()->create();

        // Court (2 jours)
        Contract::factory()->create([
            'vehicle_id' => $v1->id, 'company_id' => $company->id,
            'start_date' => '2025-01-01', 'end_date' => '2025-01-02',
        ]);
        // Long (90 jours)
        Contract::factory()->create([
            'vehicle_id' => $v2->id, 'company_id' => $company->id,
            'start_date' => '2025-01-01', 'end_date' => '2025-03-31',
        ]);

        $this->actingAs($user)
            ->get('/app/contracts?sortKey=duration&sortDirection=desc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contracts.data.0.durationDays', 90)
                ->where('contracts.data.1.durationDays', 2),
            );
    }

    #[Test]
    public function index_query_dto_est_renvoye_au_frontend(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $driver = Driver::factory()->create();

        $url = sprintf(
            '/app/contracts?perPage=50&sortKey=startDate&sortDirection=desc&search=foo&vehicleId=%d&companyId=%d&driverId=%d&type=lcd&periodStart=2025-01-01&periodEnd=2025-12-31',
            $vehicle->id,
            $company->id,
            $driver->id,
        );

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('query.perPage', 50)
                ->where('query.sortKey', 'startDate')
                ->where('query.sortDirection', 'desc')
                ->where('query.search', 'foo')
                ->where('query.vehicleId', $vehicle->id)
                ->where('query.companyId', $company->id)
                ->where('query.driverId', $driver->id)
                ->where('query.type', 'lcd')
                ->where('query.periodStart', '2025-01-01')
                ->where('query.periodEnd', '2025-12-31'),
            );
    }

    #[Test]
    public function index_refuse_l_acces_aux_invites(): void
    {
        $this->get('/app/contracts')->assertRedirect('/login');
    }

    #[Test]
    public function show_renvoie_le_dto_du_contrat_et_le_breakdown_fiscal_lcd(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',  // 15 j → LCD → 0 €
                'contract_type' => ContractType::Lcd,
            ]);

        $this->actingAs($user)
            ->get("/app/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Contracts/Show/Index')
                ->has('contract', fn (AssertableInertia $c) => $c
                    ->where('id', $contract->id)
                    ->where('startDate', '2024-03-01')
                    ->where('endDate', '2024-03-15')
                    ->where('durationDays', 15)
                    ->has('companyColor')  // ajout 04.M : prop nécessaire au CompanyTag du KPI Entreprise
                    ->etc())
                ->has('taxBreakdown', fn (AssertableInertia $b) => $b
                    ->where('totalDue', fn (mixed $v): bool => (float) $v === 0.0)
                    ->has('years', 1)
                    ->has('years.0', fn (AssertableInertia $y) => $y
                        ->where('year', 2024)
                        ->where('daysAssigned', 0)  // tous jours retirés par R-2024-021 (LCD)
                        ->where('totalDue', fn (mixed $v): bool => (float) $v === 0.0)
                        ->etc()))
                ->has('documents', 0));  // ajout 04.N : prop liste documents PDF (vide ici)
    }

    #[Test]
    public function show_breakdown_fiscal_lld_60_jours_a_cheval_sur_deux_mois(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        // 60 jours à cheval mai-juin → LLD → taxable
        $contract = Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-05-01',
                'end_date' => '2024-06-29',
                'contract_type' => ContractType::Lld,
            ]);

        $this->actingAs($user)
            ->get("/app/contracts/{$contract->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('taxBreakdown', fn (AssertableInertia $b) => $b
                    ->where('totalDue', fn (float $v): bool => $v > 0.0)
                    ->has('years.0', fn (AssertableInertia $y) => $y
                        ->where('daysAssigned', fn (int $v): bool => $v > 0)
                        ->where('totalDue', fn (float $v): bool => $v > 0.0)
                        ->etc())));
    }

    #[Test]
    public function show_renvoie_404_si_contrat_inexistant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/contracts/999999')->assertNotFound();
    }

    #[Test]
    public function store_cree_un_contrat_et_redirige_vers_show(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $payload = [
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => null,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_reference' => 'REF-001',
            'contract_type' => 'lcd',
            'notes' => null,
        ];

        $this->actingAs($user)
            ->post('/app/contracts', $payload)
            ->assertSessionHas('toast-success', 'Contrat enregistré.');

        $this->assertDatabaseHas('contracts', [
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_reference' => 'REF-001',
            'contract_type' => 'lcd',
        ]);
    }

    #[Test]
    public function store_refuse_si_la_date_de_fin_est_avant_la_date_de_debut(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/app/contracts', [
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => '2024-03-15',
                'end_date' => '2024-03-01',
                'contract_reference' => null,
                'contract_type' => 'lcd',
                'notes' => null,
            ])
            ->assertSessionHasErrors(['end_date']);

        $this->assertSame(0, Contract::query()->count());
    }

    #[Test]
    public function store_remonte_un_message_fr_si_overlap(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        // Le handler global (cf. bootstrap/app.php) convertit
        // ContractOverlapException en flash `toast-error` + back().
        $this->actingAs($user)
            ->post('/app/contracts', [
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => '2024-03-10',
                'end_date' => '2024-03-25',
                'contract_reference' => null,
                'contract_type' => 'lcd',
                'notes' => null,
            ])
            ->assertRedirect();

        $this->assertSame(1, Contract::query()->count());
        $this->assertNotNull(session('toast-error'));
    }

    #[Test]
    public function update_modifie_les_bornes_d_un_contrat(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $contract = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_type' => ContractType::Lcd,
        ]);

        $this->actingAs($user)
            ->patch("/app/contracts/{$contract->id}", [
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => '2024-03-05',
                'end_date' => '2024-03-25',
                'contract_reference' => null,
                'contract_type' => 'lcd',
                'notes' => null,
            ])
            ->assertRedirect("/app/contracts/{$contract->id}")
            ->assertSessionHas('toast-success', 'Contrat mis à jour.');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'start_date' => '2024-03-05',
            'end_date' => '2024-03-25',
        ]);
    }

    #[Test]
    public function destroy_soft_delete_le_contrat(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $this->actingAs($user)
            ->delete("/app/contracts/{$contract->id}")
            ->assertRedirect('/app/contracts')
            ->assertSessionHas('toast-success', 'Contrat supprimé.');

        $this->assertSoftDeleted($contract);
    }

    #[Test]
    public function create_expose_busy_dates_par_vehicule_pour_le_picker(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        // Contrat actif courant → ses jours doivent figurer dans busyDates.
        $today = now()->toImmutable();
        $start = $today->startOfMonth()->toDateString();
        $end = $today->startOfMonth()->addDays(9)->toDateString();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->actingAs($user)
            ->get('/app/contracts/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Contracts/Create/Index')
                ->where(
                    'busyDatesByVehicleId',
                    function (mixed $busyMap) use ($vehicle, $start, $end): bool {
                        $byVehicle = collect($busyMap)->all();

                        return isset($byVehicle[$vehicle->id])
                            && in_array($start, $byVehicle[$vehicle->id], true)
                            && in_array($end, $byVehicle[$vehicle->id], true);
                    },
                )
                ->etc(),
            );
    }

    #[Test]
    public function edit_exclut_les_dates_du_contrat_courant_de_busy_dates(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $today = now()->toImmutable();
        $start = $today->startOfMonth()->toDateString();
        $end = $today->startOfMonth()->addDays(9)->toDateString();
        $contract = Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->actingAs($user)
            ->get("/app/contracts/{$contract->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Contracts/Edit/Index')
                ->where(
                    'busyDatesByVehicleId',
                    function (mixed $busyMap) use ($vehicle, $start): bool {
                        $byVehicle = collect($busyMap)->all();

                        // Soit pas de busy pour ce véhicule (cas trivial),
                        // soit les dates du contrat courant ne s'y trouvent
                        // pas (cas edit avec d'autres contrats existants).
                        if (! isset($byVehicle[$vehicle->id])) {
                            return true;
                        }

                        return ! in_array($start, $byVehicle[$vehicle->id], true);
                    },
                )
                ->etc(),
            );
    }

    #[Test]
    public function bulk_store_cree_n_contrats_pour_n_vehicules(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/contracts/bulk', [
                'vehicle_ids' => [$vehicleA->id, $vehicleB->id],
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-15',
                'contract_reference' => null,
                'contract_type' => 'lcd',
                'notes' => null,
            ])
            ->assertSessionHas('toast-success', '2 contrats enregistrés.');

        $this->assertSame(2, Contract::query()->count());
    }
}
