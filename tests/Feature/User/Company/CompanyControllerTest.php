<?php

declare(strict_types=1);

namespace Tests\Feature\User\Company;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\AssertsPaginatedIndex;
use Tests\TestCase;

final class CompanyControllerTest extends TestCase
{
    use AssertsPaginatedIndex;
    use RefreshDatabase;

    #[Test]
    public function index_expose_has_any_company_pour_decider_du_placeholder_initial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasAnyCompany', false));

        Company::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasAnyCompany', true));
    }

    #[Test]
    public function index_liste_les_entreprises(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/app/companies')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $page->component('User/Companies/Index/Index');
                $this->assertPaginatedShape(
                    $page,
                    'companies',
                    expectedDataCount: 3,
                    expectedMeta: ['total' => 3, 'currentPage' => 1, 'perPage' => 20],
                );
            });
    }

    #[Test]
    public function index_paginate_avec_per_page_personnalise(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(25)->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=10')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 10,
                expectedMeta: ['total' => 25, 'lastPage' => 3, 'perPage' => 10],
            ));
    }

    #[Test]
    public function index_navigation_page_2(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(25)->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=10&page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 10,
                expectedMeta: ['currentPage' => 2, 'from' => 11, 'to' => 20],
            ));
    }

    #[Test]
    public function index_per_page_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=33')
            ->assertSessionHasErrors(['perPage']);
    }

    #[Test]
    public function index_sort_key_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        // 'tax' était une sortKey client-side avant ADR-0020 ; elle est
        // volontairement absente de la whitelist server-side car non SQL.
        $this->actingAs($user)
            ->get('/app/companies?sortKey=tax')
            ->assertSessionHasErrors(['sortKey']);
    }

    #[Test]
    public function index_sort_par_short_code_desc(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['short_code' => 'AAA', 'legal_name' => 'A']);
        Company::factory()->create(['short_code' => 'CCC', 'legal_name' => 'C']);
        Company::factory()->create(['short_code' => 'BBB', 'legal_name' => 'B']);

        $this->actingAs($user)
            ->get('/app/companies?sortKey=shortCode&sortDirection=desc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.shortCode', 'CCC')
                ->where('companies.data.1.shortCode', 'BBB')
                ->where('companies.data.2.shortCode', 'AAA'),
            );
    }

    #[Test]
    public function index_filtre_is_active(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['is_active' => true, 'legal_name' => 'Active 1']);
        Company::factory()->create(['is_active' => true, 'legal_name' => 'Active 2']);
        Company::factory()->create(['is_active' => false, 'legal_name' => 'Inactive']);

        $this->actingAs($user)
            ->get('/app/companies?isActive=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 2,
                expectedMeta: ['total' => 2],
            ));

        $this->actingAs($user)
            ->get('/app/companies?isActive=0')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'companies',
                expectedDataCount: 1,
                expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_filtre_contracts_scope(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $avec = Company::factory()->create(['legal_name' => 'Avec contrats']);
        Contract::factory()->forVehicle($vehicle)->forCompany($avec)->create();

        Company::factory()->create(['legal_name' => 'Sans contrats']);

        $this->actingAs($user)
            ->get('/app/companies?contractsScope=with')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.meta.total', 1)
                ->where('companies.data.0.legalName', 'Avec contrats'),
            );

        $this->actingAs($user)
            ->get('/app/companies?contractsScope=without')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.meta.total', 1)
                ->where('companies.data.0.legalName', 'Sans contrats'),
            );
    }

    #[Test]
    public function index_filtre_company_type(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['is_individual_business' => false, 'legal_name' => 'Société']);
        Company::factory()->create(['is_individual_business' => true, 'legal_name' => 'EI Dupont']);

        $this->actingAs($user)
            ->get('/app/companies?companyType=corporate')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.meta.total', 1)
                ->where('companies.data.0.legalName', 'Société'),
            );

        $this->actingAs($user)
            ->get('/app/companies?companyType=individual')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.meta.total', 1)
                ->where('companies.data.0.legalName', 'EI Dupont'),
            );
    }

    #[Test]
    public function index_filtre_city_like(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['city' => 'Lyon', 'legal_name' => 'Lyon Co']);
        Company::factory()->create(['city' => 'Paris', 'legal_name' => 'Paris Co']);

        $this->actingAs($user)
            ->get('/app/companies?city=lyon')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.meta.total', 1)
                ->where('companies.data.0.legalName', 'Lyon Co'),
            );
    }

    #[Test]
    public function index_search_filtre_par_short_code_legal_name_ou_siren(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['legal_name' => 'Acme SAS', 'short_code' => 'ACS', 'siren' => '111222333']);
        Company::factory()->create(['legal_name' => 'Beta Corp', 'short_code' => 'BCO', 'siren' => '444555666']);
        Company::factory()->create(['legal_name' => 'Gamma SARL', 'short_code' => 'GSA', 'siren' => '777888999']);

        // Search par legal_name
        $this->actingAs($user)
            ->get('/app/companies?search=Acme')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.legalName', 'Acme SAS'),
            );

        // Search par short_code
        $this->actingAs($user)
            ->get('/app/companies?search=BCO')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.legalName', 'Beta Corp'),
            );

        // Search par siren partiel
        $this->actingAs($user)
            ->get('/app/companies?search=777')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('companies.data.0.legalName', 'Gamma SARL'),
            );
    }

    #[Test]
    public function index_query_dto_est_renvoye_au_frontend(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies?perPage=50&sortKey=legalName&sortDirection=desc&search=foo&isActive=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('query.perPage', 50)
                ->where('query.sortKey', 'legalName')
                ->where('query.sortDirection', 'desc')
                ->where('query.search', 'foo')
                ->where('query.isActive', true),
            );
    }

    #[Test]
    public function create_renvoie_les_couleurs_disponibles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Companies/Create/Index')
                ->has('colors'),
            );
    }

    #[Test]
    public function store_cree_une_entreprise_avec_short_code_auto_genere(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/companies', [
                'legal_name' => 'Acme SAS',
                'color' => 'indigo',
                'country' => 'FR',
            ])
            ->assertRedirect('/app/companies');

        // 'Acme SAS' = 2 mots → 1ère du 1er + 2 premières du 2e = 'ASA'
        $this->assertDatabaseHas('companies', [
            'legal_name' => 'Acme SAS',
            'short_code' => 'ASA',
        ]);
    }

    #[Test]
    public function store_refuse_la_creation_si_le_short_code_genere_collisionne(): void
    {
        $user = User::factory()->create();
        // Pré-existant avec short_code 'ASA' pour forcer la collision
        Company::factory()->create(['legal_name' => 'Pré-existant', 'short_code' => 'ASA']);

        $this->actingAs($user)
            ->post('/app/companies', [
                'legal_name' => 'Acme SAS', // génèrerait 'ASA' → collision
                'color' => 'indigo',
                'country' => 'FR',
            ])
            ->assertSessionHasErrors(['legal_name']);

        $this->assertDatabaseMissing('companies', ['legal_name' => 'Acme SAS']);
    }

    // ----------------------------------------------------------------
    // Show — chantier K (refonte fiche entreprise, ADR-0020 D3)
    // ----------------------------------------------------------------

    #[Test]
    public function show_renvoie_la_structure_complete_avec_les_nouveaux_champs(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->get("/app/companies/{$company->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Companies/Show/Index')
                ->has('company.lifetime', fn (AssertableInertia $stat) => $stat
                    ->where('daysUsed', 0)
                    ->where('contractsCount', 0)
                    ->where('taxesGenerated', 0)
                    ->where('rentTotal', null),
                )
                ->has('company.history', 0)
                ->has('company.activityByYear', 0)
                ->has('company.availableYears', 0)
                ->where('company.currentRealYear', (int) Carbon::now()->year),
            );
    }

    #[Test]
    public function show_activity_calcule_la_heatmap_mensuelle_par_an(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // 5 jours en mars 2024 (mois index 2) sur ce véhicule
        Contract::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-05',
        ]);

        // 3 jours en juillet 2024 (mois index 6) sur le même véhicule
        Contract::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-07-10',
            'end_date' => '2024-07-12',
        ]);

        $this->actingAs($user)
            ->get("/app/companies/{$company->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('company.availableYears', [2024])
                ->has('company.activityByYear', 1)
                ->where('company.activityByYear.0.year', 2024)
                ->where('company.activityByYear.0.daysByMonth.2', 5) // mars
                ->where('company.activityByYear.0.daysByMonth.6', 3) // juillet
                ->where('company.activityByYear.0.daysByMonth.0', 0) // janvier vide
                ->where('company.activityByYear.0.daysByMonth.11', 0), // décembre vide
            );
    }

    #[Test]
    public function show_activity_top_vehicles_trie_desc_et_limite_a_3(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // 4 véhicules avec des durées d'usage distinctes en 2024
        $v1 = Vehicle::factory()->create(['license_plate' => 'AAA-001-AA', 'brand' => 'Renault', 'model' => 'Clio']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v1->id]);
        $v2 = Vehicle::factory()->create(['license_plate' => 'BBB-002-BB', 'brand' => 'Peugeot', 'model' => '3008']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2->id]);
        $v3 = Vehicle::factory()->create(['license_plate' => 'CCC-003-CC', 'brand' => 'Citroën', 'model' => 'C3']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v3->id]);
        $v4 = Vehicle::factory()->create(['license_plate' => 'DDD-004-DD', 'brand' => 'Dacia', 'model' => 'Sandero']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v4->id]);

        // v1 = 30 jours, v2 = 20 jours, v3 = 10 jours, v4 = 5 jours
        Contract::factory()->create([
            'company_id' => $company->id, 'vehicle_id' => $v1->id,
            'start_date' => '2024-01-01', 'end_date' => '2024-01-30',
        ]);
        Contract::factory()->create([
            'company_id' => $company->id, 'vehicle_id' => $v2->id,
            'start_date' => '2024-02-01', 'end_date' => '2024-02-20',
        ]);
        Contract::factory()->create([
            'company_id' => $company->id, 'vehicle_id' => $v3->id,
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        Contract::factory()->create([
            'company_id' => $company->id, 'vehicle_id' => $v4->id,
            'start_date' => '2024-04-01', 'end_date' => '2024-04-05',
        ]);

        $this->actingAs($user)
            ->get("/app/companies/{$company->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('company.activityByYear.0.topVehicles', 3) // tronqué à 3
                ->where('company.activityByYear.0.topVehicles.0.licensePlate', 'AAA-001-AA')
                ->where('company.activityByYear.0.topVehicles.0.daysUsed', 30)
                ->where('company.activityByYear.0.topVehicles.1.licensePlate', 'BBB-002-BB')
                ->where('company.activityByYear.0.topVehicles.1.daysUsed', 20)
                ->where('company.activityByYear.0.topVehicles.2.licensePlate', 'CCC-003-CC')
                ->where('company.activityByYear.0.topVehicles.2.daysUsed', 10),
            );
    }

    // ----------------------------------------------------------------
    // Show — chantier N.1 (onglet Contrats avec table paginée server-side)
    // ----------------------------------------------------------------

    #[Test]
    public function show_expose_contracts_pagines_pour_l_onglet_contrats(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // 25 contrats sur 25 véhicules distincts pour éviter le trigger
        // SQL `contracts_no_overlap_*` (invariant ADR-0019 D3).
        for ($i = 0; $i < 25; $i++) {
            $vehicle = Vehicle::factory()->create();
            VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create();
        }

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'contracts',
                expectedDataCount: 20,
                expectedMeta: ['total' => 25, 'lastPage' => 2, 'perPage' => 20],
            ));
    }

    #[Test]
    public function show_force_le_company_id_meme_si_query_envoie_un_autre(): void
    {
        // Un attaquant pourrait tenter de passer ?companyId=X en query
        // pour scope les contrats sur une autre entreprise. La fiche
        // Company doit imposer son propre scope.
        $user = User::factory()->create();
        $companyA = Company::factory()->create(['legal_name' => 'A']);
        $companyB = Company::factory()->create(['legal_name' => 'B']);
        // Véhicule distinct par contrat → pas de collision overlap.
        $vA = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vA->id]);
        $vB1 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vB1->id]);
        $vB2 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vB2->id]);
        $vB3 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vB3->id]);

        Contract::factory()->forVehicle($vA)->forCompany($companyA)->create();
        Contract::factory()->forVehicle($vB1)->forCompany($companyB)->create();
        Contract::factory()->forVehicle($vB2)->forCompany($companyB)->create();
        Contract::factory()->forVehicle($vB3)->forCompany($companyB)->create();

        $this->actingAs($user)
            ->get('/app/companies/'.$companyA->id.'?companyId='.$companyB->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contracts.meta.total', 1)
                ->where('contracts.data.0.companyId', $companyA->id),
            );
    }

    #[Test]
    public function show_filtre_contracts_par_periode(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // Contrat 2024 (hors plage)
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-31',
        ]);
        // Contrat 2025 (dans plage)
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2025-06-01', 'end_date' => '2025-06-30',
        ]);

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id.'?periodStart=2025-01-01&periodEnd=2025-12-31')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contracts.meta.total', 1)
                ->where('contracts.data.0.startDate', '2025-06-01'),
            );
    }

    #[Test]
    public function show_expose_stats_contractuelles_lifetime_sans_filtre(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $v1 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v1->id]);
        $v2 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2->id]);

        // 10 jours LCD + 31 jours LLD = 41 jours, 1 LCD, 1 LLD
        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2024-01-01', 'end_date' => '2024-01-10',
            'contract_type' => 'lcd',
        ]);
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => '2024-02-01', 'end_date' => '2024-03-02',
            'contract_type' => 'lld',
        ]);

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contractsStats.totalDays', 41)
                ->where('contractsStats.lcdCount', 1)
                ->where('contractsStats.lldCount', 1),
            );
    }

    #[Test]
    public function show_stats_contractuelles_clamp_les_jours_a_la_periode_filtree(): void
    {
        // Un contrat 01/01–31/12 affiché dans un filtre Q3 2024 doit
        // compter ~92 jours (juillet–septembre), pas 365.
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $v = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v->id]);

        Contract::factory()->forVehicle($v)->forCompany($company)->create([
            'start_date' => '2024-01-01', 'end_date' => '2024-12-31',
            'contract_type' => 'lld',
        ]);

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id.'?periodStart=2024-07-01&periodEnd=2024-09-30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contractsStats.totalDays', 92) // 31 + 31 + 30
                ->where('contractsStats.lcdCount', 0)
                ->where('contractsStats.lldCount', 1),
            );
    }

    #[Test]
    public function show_expose_plage_continue_des_annees_de_first_contract_a_current_year(): void
    {
        // Premier contrat en 2022 → plage attendue [2022, 2023, ..., currentYear].
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $v = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v->id]);

        Contract::factory()->forVehicle($v)->forCompany($company)->create([
            'start_date' => '2022-06-01', 'end_date' => '2022-08-31',
        ]);

        $expectedRange = range(2022, (int) Carbon::now()->year);

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contractsAvailableYears', $expectedRange),
            );
    }

    #[Test]
    public function show_plage_annees_vide_quand_aucun_contrat(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contractsAvailableYears', []),
            );
    }

    #[Test]
    public function show_expose_options_drivers_pour_le_picker_du_modal_add(): void
    {
        // Chantier M.2 : `AddCompanyDriverModal` peuple son `<SelectInput>`
        // depuis `props.options.drivers`. Le filtrage des drivers déjà
        // rattachés vit côté front (cf. `filterAvailableDrivers`).
        $user = User::factory()->create();
        $company = Company::factory()->create();
        Driver::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/app/companies/'.$company->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('options.drivers', 3)
                ->has('options.drivers.0', fn (AssertableInertia $d) => $d
                    ->has('id')
                    ->has('fullName')
                    ->has('initials'),
                ),
            );
    }

    #[Test]
    public function show_history_inclut_uniquement_les_annees_avec_contrat(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // Contrat en 2024 → 2024 doit figurer dans history
        Contract::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        // Contrat en 2025 → 2025 aussi
        $vehicle2 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle2->id]);
        Contract::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle2->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ]);

        $this->actingAs($user)
            ->get("/app/companies/{$company->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('company.history', 2)
                ->where('company.history.0.year', 2024)
                ->where('company.history.1.year', 2025)
                ->where('company.lifetime.contractsCount', 2),
            );
    }
}
