<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\AssertsPaginatedIndex;
use Tests\TestCase;

final class VehicleControllerTest extends TestCase
{
    use AssertsPaginatedIndex;
    use RefreshDatabase;

    #[Test]
    public function index_expose_has_any_vehicle_pour_decider_du_placeholder_initial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasAnyVehicle', false));

        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasAnyVehicle', true));
    }

    #[Test]
    public function index_liste_les_vehicules_avec_cout_plein_annee_et_taux_journalier(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($vehicle): void {
                $page->component('User/Vehicles/Index/Index');
                $this->assertPaginatedShape(
                    $page,
                    'vehicles',
                    expectedDataCount: 1,
                    expectedMeta: ['total' => 1, 'currentPage' => 1, 'perPage' => 20],
                );
                $page->has('vehicles.data.0', fn (AssertableInertia $v) => $v
                    ->where('id', $vehicle->id)
                    ->where('licensePlate', $vehicle->license_plate)
                    ->has('fullYearTax')
                    ->has('dailyTaxRate')
                    ->etc(),
                );
            });
    }

    #[Test]
    public function index_paginate_avec_per_page_personnalise(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $vehicle = Vehicle::factory()->create();
            VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        }

        $this->actingAs($user)
            ->get('/app/vehicles?perPage=10')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'vehicles',
                expectedDataCount: 10,
                expectedMeta: ['total' => 25, 'lastPage' => 3, 'perPage' => 10],
            ));
    }

    #[Test]
    public function index_per_page_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles?perPage=33')
            ->assertSessionHasErrors(['perPage']);
    }

    #[Test]
    public function index_sort_full_year_tax_hors_whitelist_rejette_la_requete(): void
    {
        $user = User::factory()->create();

        // fullYearTax était triable avant ADR-0020 ; volontairement absent
        // de la whitelist server-side car valeur calculée non SQL (D6).
        $this->actingAs($user)
            ->get('/app/vehicles?sortKey=fullYearTax')
            ->assertSessionHasErrors(['sortKey']);
    }

    #[Test]
    public function index_sort_par_license_plate_desc(): void
    {
        $user = User::factory()->create();
        $a = Vehicle::factory()->create(['license_plate' => 'AA-111-AA']);
        $c = Vehicle::factory()->create(['license_plate' => 'CC-333-CC']);
        $b = Vehicle::factory()->create(['license_plate' => 'BB-222-BB']);
        foreach ([$a, $b, $c] as $v) {
            VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v->id]);
        }

        $this->actingAs($user)
            ->get('/app/vehicles?sortKey=licensePlate&sortDirection=desc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.data.0.licensePlate', 'CC-333-CC')
                ->where('vehicles.data.1.licensePlate', 'BB-222-BB')
                ->where('vehicles.data.2.licensePlate', 'AA-111-AA'),
            );
    }

    #[Test]
    public function index_filtre_status(): void
    {
        $user = User::factory()->create();
        $active = Vehicle::factory()->create(['current_status' => 'active']);
        $maintenance = Vehicle::factory()->create(['current_status' => 'maintenance']);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $active->id]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $maintenance->id]);

        $this->actingAs($user)
            ->get('/app/vehicles?status=active')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatedShape(
                $page,
                'vehicles',
                expectedDataCount: 1,
                expectedMeta: ['total' => 1],
            ));
    }

    #[Test]
    public function index_search_filtre_par_plate_brand_ou_model(): void
    {
        $user = User::factory()->create();
        $renault = Vehicle::factory()->create(['license_plate' => 'AA-111-AA', 'brand' => 'Renault', 'model' => 'Clio']);
        $peugeot = Vehicle::factory()->create(['license_plate' => 'BB-222-BB', 'brand' => 'Peugeot', 'model' => '208']);
        $citroen = Vehicle::factory()->create(['license_plate' => 'CC-333-CC', 'brand' => 'Citroën', 'model' => 'C3']);
        foreach ([$renault, $peugeot, $citroen] as $v) {
            VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v->id]);
        }

        // Search par plate
        $this->actingAs($user)
            ->get('/app/vehicles?search=BB-222')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.data.0.licensePlate', 'BB-222-BB'),
            );

        // Search par brand
        $this->actingAs($user)
            ->get('/app/vehicles?search=Renault')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.data.0.brand', 'Renault'),
            );

        // Search par model
        $this->actingAs($user)
            ->get('/app/vehicles?search=C3')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.data.0.model', 'C3'),
            );
    }

    #[Test]
    public function index_filtre_energy_source(): void
    {
        $user = User::factory()->create();
        $electric = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $electric->id,
            'energy_source' => 'electric',
        ]);

        $diesel = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $diesel->id,
            'energy_source' => 'diesel',
        ]);

        $this->actingAs($user)
            ->get('/app/vehicles?energySource=electric')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.meta.total', 1)
                ->where('vehicles.data.0.id', $electric->id),
            );
    }

    #[Test]
    public function index_filtre_pollutant_category(): void
    {
        $user = User::factory()->create();
        $catE = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $catE->id,
            'energy_source' => 'electric',
            'pollutant_category' => 'e',
        ]);

        $catPolluting = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $catPolluting->id,
            'energy_source' => 'diesel',
            'pollutant_category' => 'most_polluting',
        ]);

        $this->actingAs($user)
            ->get('/app/vehicles?pollutantCategory=e')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.meta.total', 1)
                ->where('vehicles.data.0.id', $catE->id),
            );
    }

    #[Test]
    public function index_filtre_handicap_access(): void
    {
        $user = User::factory()->create();
        $handicap = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $handicap->id,
            'handicap_access' => true,
        ]);

        $standard = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $standard->id,
            'handicap_access' => false,
        ]);

        $this->actingAs($user)
            ->get('/app/vehicles?handicapAccess=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.meta.total', 1)
                ->where('vehicles.data.0.id', $handicap->id),
            );
    }

    #[Test]
    public function index_filtre_first_registration_year_range(): void
    {
        $user = User::factory()->create();
        $v2018 = Vehicle::factory()->create([
            'first_french_registration_date' => '2018-06-15',
            'first_origin_registration_date' => '2018-06-15',
            'first_economic_use_date' => '2018-06-15',
            'acquisition_date' => '2018-06-15',
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2018->id]);
        $v2020 = Vehicle::factory()->create([
            'first_french_registration_date' => '2020-03-10',
            'first_origin_registration_date' => '2020-03-10',
            'first_economic_use_date' => '2020-03-10',
            'acquisition_date' => '2020-03-10',
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2020->id]);
        $v2023 = Vehicle::factory()->create([
            'first_french_registration_date' => '2023-11-20',
            'first_origin_registration_date' => '2023-11-20',
            'first_economic_use_date' => '2023-11-20',
            'acquisition_date' => '2023-11-20',
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2023->id]);

        // Min seul : >= 2020 → v2020 + v2023
        $this->actingAs($user)
            ->get('/app/vehicles?firstRegistrationYearMin=2020')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.meta.total', 2),
            );

        // Max seul : <= 2020 → v2018 + v2020
        $this->actingAs($user)
            ->get('/app/vehicles?firstRegistrationYearMax=2020')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.meta.total', 2),
            );

        // Range Min + Max : exactement 2020
        $this->actingAs($user)
            ->get('/app/vehicles?firstRegistrationYearMin=2020&firstRegistrationYearMax=2020')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.meta.total', 1)
                ->where('vehicles.data.0.id', $v2020->id),
            );
    }

    #[Test]
    public function index_renvoie_first_registration_year_bounds(): void
    {
        $user = User::factory()->create();
        $v1 = Vehicle::factory()->create([
            'first_french_registration_date' => '2018-06-15',
            'first_origin_registration_date' => '2018-06-15',
            'first_economic_use_date' => '2018-06-15',
            'acquisition_date' => '2018-06-15',
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v1->id]);
        $v2 = Vehicle::factory()->create([
            'first_french_registration_date' => '2023-11-20',
            'first_origin_registration_date' => '2023-11-20',
            'first_economic_use_date' => '2023-11-20',
            'acquisition_date' => '2023-11-20',
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $v2->id]);

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.firstRegistrationYearBounds.min', 2018)
                ->where('options.firstRegistrationYearBounds.max', 2023),
            );
    }

    #[Test]
    public function index_renvoie_first_registration_year_bounds_null_si_flotte_vide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.firstRegistrationYearBounds', null),
            );
    }

    #[Test]
    public function index_query_dto_est_renvoye_au_frontend(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles?perPage=50&sortKey=licensePlate&sortDirection=desc&search=foo&includeExited=1&status=active')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('query.perPage', 50)
                ->where('query.sortKey', 'licensePlate')
                ->where('query.sortDirection', 'desc')
                ->where('query.search', 'foo')
                ->where('query.includeExited', true)
                ->where('query.status', 'active'),
            );
    }

    #[Test]
    public function create_renvoie_les_options_de_formulaire(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Create/Index')
                ->has('options', fn (AssertableInertia $o) => $o
                    ->has('receptionCategories')
                    ->has('vehicleUserTypes')
                    ->has('bodyTypes')
                    ->has('energySources')
                    ->has('underlyingCombustionEngineTypes')
                    ->has('euroStandards')
                    ->has('homologationMethods')
                    ->has('pollutantCategories')),
            );
    }

    #[Test]
    public function show_renvoie_la_vue_du_vehicule_avec_caracteristiques_fiscales_courantes(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'AB-456-CD',
            'brand' => 'Renault',
            'model' => 'Megane',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Show/Index')
                ->has('vehicle', fn (AssertableInertia $v) => $v
                    ->where('id', $vehicle->id)
                    ->where('licensePlate', 'AB-456-CD')
                    ->where('brand', 'Renault')
                    ->where('model', 'Megane')
                    ->has('currentFiscalCharacteristics', fn (AssertableInertia $f) => $f
                        ->where('id', $current->id)
                        ->where('isCurrent', true)
                        ->where('effectiveFrom', '2024-01-01')
                        ->where('effectiveTo', null)
                        ->etc())
                    ->has('fiscalCharacteristicsHistory', 1)
                    ->has('usageStats', fn (AssertableInertia $s) => $s
                        ->has('fiscalYear')
                        ->has('daysInYear')
                        ->where('daysUsedThisYear', 0)
                        ->where('actualTaxThisYear', 0)
                        ->has('fullYearTax')
                        ->has('dailyTaxRate')
                        ->has('companies', 0)
                        ->has('weeklyBreakdown')
                        ->has('fullYearTaxBreakdown', fn (AssertableInertia $b) => $b
                            ->has('daysInYear')
                            ->has('total')
                            ->has('appliedExemptions')
                            ->has('appliedRuleCodes')
                            ->has('appliedRules')
                            ->has('taxSegments', 1, fn (AssertableInertia $s) => $s
                                ->has('effectiveFromInYear')
                                ->has('effectiveToInYear')
                                ->has('daysInSegment')
                                ->has('vfc')
                                ->has('co2Method')
                                ->has('co2FullYearTariff')
                                ->has('co2Explanation')
                                ->has('co2Due')
                                ->has('pollutantCategory')
                                ->has('pollutantsFullYearTariff')
                                ->has('pollutantsExplanation')
                                ->has('pollutantsDue')
                                ->has('appliedExemptions')
                                ->has('appliedRuleCodes'))))
                    ->has('busyDates')
                    ->etc()),
            );
    }

    #[Test]
    public function show_inclut_breakdown_par_entreprise_utilisatrice_trie_par_jours(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $year = 2024;

        $companyA = Company::factory()->create(['short_code' => 'ALPH']);
        $companyB = Company::factory()->create(['short_code' => 'BETA']);

        // 35 jours pour A, 60 jours pour B → B doit apparaître en
        // premier (tri desc). Contrats non-LCD (durée > 30, pas mois
        // civil entier) pour produire un breakdown taxable visible.
        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => sprintf('%04d-01-15', $year),
            'end_date' => sprintf('%04d-02-18', $year),
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => sprintf('%04d-04-15', $year),
            'end_date' => sprintf('%04d-06-13', $year),
        ]);

        // L'endpoint show() ne lit plus `?year=` depuis Phase 2 onglets
        // (lazy fetch via `/usage-stats`). On test directement l'endpoint
        // JSON lazy pour un breakdown sur l'année voulue.
        $response = $this->actingAs($user)
            ->getJson("/app/vehicles/{$vehicle->id}/usage-stats?year={$year}")
            ->assertOk();

        $payload = $response->json();
        $this->assertSame($year, $payload['fiscalYear']);
        $this->assertSame(95, $payload['daysUsedThisYear']);
        $this->assertCount(2, $payload['companies']);
        $this->assertSame('BETA', $payload['companies'][0]['shortCode']);
        $this->assertSame(60, $payload['companies'][0]['daysUsed']);
        $this->assertSame('ALPH', $payload['companies'][1]['shortCode']);
        $this->assertSame(35, $payload['companies'][1]['daysUsed']);
    }

    #[Test]
    public function show_inclut_l_historique_complet_des_periodes_fiscales(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // 3 versions historisées : la plus récente est courante.
        $oldest = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2022-12-31',
        ]);
        $middle = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Show/Index')
                ->has('vehicle.fiscalCharacteristicsHistory', 3)
                // Tri antéchronologique : la version courante en premier.
                ->where('vehicle.fiscalCharacteristicsHistory.0.id', $current->id)
                ->where('vehicle.fiscalCharacteristicsHistory.0.isCurrent', true)
                ->where('vehicle.fiscalCharacteristicsHistory.1.id', $middle->id)
                ->where('vehicle.fiscalCharacteristicsHistory.1.isCurrent', false)
                ->where('vehicle.fiscalCharacteristicsHistory.2.id', $oldest->id)
                ->where('vehicle.fiscalCharacteristicsHistory.2.isCurrent', false),
            );
    }

    #[Test]
    public function show_renvoie_404_si_vehicule_inexistant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/vehicles/999999')
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // Show — chantier η Phase 2 (doctrine temporelle 3 lentilles)
    // ----------------------------------------------------------------

    #[Test]
    public function show_kpi_year_est_l_annee_calendaire_courante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicle.kpiYear', (int) Carbon::now()->year)
                ->has('vehicle.kpiStats', fn (AssertableInertia $s) => $s
                    ->where('year', (int) Carbon::now()->year)
                    ->where('daysUsed', 0)
                    ->where('contractsCount', 0)
                    ->has('actualTax')
                    ->has('fullYearTax')),
            );
    }

    #[Test]
    public function show_kpi_fiscal_available_false_si_pas_de_regles_pour_l_annee_courante(): void
    {
        // En 2026, seules les règles 2024 sont codées dans le registry.
        // `kpiFiscalAvailable` doit être false pour qu'à l'UI les KPI
        // Taxes/Coût plein affichent "—" + caption "Règles non implémentées".
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicle.kpiFiscalAvailable', false),
            );
    }

    #[Test]
    public function show_history_couvre_les_annees_passees_du_scope_global(): void
    {
        // Crée un contrat 2024 → minYear = 2024, currentYear = 2026
        // → history doit contenir [2024 réel, 2025 neutre], pas 2026.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        $currentYear = (int) Carbon::now()->year;
        $expectedYears = range(2024, $currentYear - 1);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicle.history', count($expectedYears))
                // Le service ordonne DESC : index 0 = currentYear-1
                // (neutre si currentYear > 2025) ou 2024 lui-même.
                ->where('vehicle.history.0.year', $currentYear - 1)
                ->where('vehicle.history.'.(count($expectedYears) - 1).'.year', 2024)
                ->where('vehicle.history.'.(count($expectedYears) - 1).'.daysUsed', 15),
            );
    }

    #[Test]
    public function show_year_scope_et_selected_year_exposes(): void
    {
        // `yearScope` = scope global (currentYear/minYear/availableYears)
        // — toutes les années où il y a au moins un contrat en BDD.
        // `selectedYear` = `currentYear` par défaut (cohérent doctrine
        // « année par défaut = année en cours, navigable sur scope global »).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);

        $currentYear = (int) Carbon::now()->year;

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicle.yearScope', fn (AssertableInertia $scope) => $scope
                    ->where('currentYear', $currentYear)
                    ->where('minYear', 2024)
                    ->has('availableYears'))
                ->where('vehicle.selectedYear', $currentYear),
            );
    }

    #[Test]
    public function lazy_endpoint_usage_stats_accepte_annee_sans_regles_fiscales(): void
    {
        // Doctrine « données métier ⊥ règles fiscales » : l'endpoint
        // lazy `/usage-stats` accepte n'importe quelle année et tolère
        // une année sans règles fiscales codées (Timeline OK, jours
        // bruts intacts, taxes à 0).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-15',
        ]);

        $payload = $this->actingAs($user)
            ->getJson("/app/vehicles/{$vehicle->id}/usage-stats?year=2025")
            ->assertOk()
            ->json();

        $this->assertSame(2025, $payload['fiscalYear']);
        $this->assertSame(15, $payload['daysUsedThisYear']);
        $this->assertSame(0, $payload['fullYearTax']);
        $this->assertSame(0, $payload['actualTaxThisYear']);
    }

    #[Test]
    public function lazy_endpoint_full_year_breakdown_retourne_dto_neutre_si_pas_de_regles(): void
    {
        // L'endpoint `/full-year-breakdown` retourne un DTO neutre
        // (tarifs 0, message « Règles non implémentées ») pour les
        // années sans règles fiscales codées.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $payload = $this->actingAs($user)
            ->getJson("/app/vehicles/{$vehicle->id}/full-year-breakdown?year=2025")
            ->assertOk()
            ->json();

        $this->assertEquals(0, $payload['total']);
        // Mode "année non supportée" : un segment placeholder couvrant
        // l'année avec tarifs/dûs à 0 et message explicite.
        $this->assertCount(1, $payload['taxSegments']);
        $this->assertEquals(0, $payload['taxSegments'][0]['co2FullYearTariff']);
        $this->assertEquals(0, $payload['taxSegments'][0]['co2Due']);
        $this->assertStringContainsString('non implémentées', $payload['taxSegments'][0]['co2Explanation']);
    }

    #[Test]
    public function store_cree_un_vehicule_et_ses_caracteristiques_fiscales(): void
    {
        $user = User::factory()->create();

        $payload = [
            'license_plate' => 'AA-123-BB',
            'brand' => 'Renault',
            'model' => 'Clio',
            'vin' => 'VF1ABCD12345EFGHK',
            'color' => 'Bleu',
            'first_french_registration_date' => '2020-01-15',
            'first_origin_registration_date' => '2020-01-15',
            'first_economic_use_date' => '2020-01-15',
            'acquisition_date' => '2020-01-15',
            'mileage_current' => 50000,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'body_type' => 'BB',
            'seats_count' => 5,
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6d_isc_fcm',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 110,
        ];

        $this->actingAs($user)
            ->post('/app/vehicles', $payload)
            ->assertRedirect('/app/vehicles');

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'AA-123-BB',
            'brand' => 'Renault',
        ]);

        $vehicle = Vehicle::query()->where('license_plate', 'AA-123-BB')->firstOrFail();

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'co2_wltp' => 110,
        ]);
    }

    #[Test]
    public function edit_renvoie_la_page_d_edition_avec_le_vehicule_et_les_options(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'EH-142-AZ']);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Edit/Index')
                ->has('vehicle', fn (AssertableInertia $v) => $v
                    ->where('id', $vehicle->id)
                    ->where('licensePlate', 'EH-142-AZ')
                    ->etc())
                ->has('options'),
            );
    }

    #[Test]
    public function update_cree_une_nouvelle_vfc_et_ferme_la_courante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'seats_count' => 5,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
            'seats_count' => 9,
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        // VFC initiale fermée.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_to' => '2025-05-31',
        ]);

        // Nouvelle VFC active.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'seats_count' => 9,
            'change_reason' => 'recharacterization',
        ]);
    }

    #[Test]
    public function update_identite_seule_n_insere_pas_de_nouvelle_vfc(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'mileage_current' => 30_000,
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'body_type' => 'CI',
            'seats_count' => 5,
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6d_isc_fcm',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 120,
        ]);

        // Payload : aucun changement fiscal (mêmes valeurs que la VFC
        // courante), uniquement le kilométrage qui passe à 45 000.
        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'mileage_current' => 45_000,
            'effective_from' => null,
            'change_reason' => null,
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        // Identité mise à jour.
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'mileage_current' => 45_000,
        ]);

        // VFC courante intacte, aucune nouvelle ligne créée.
        $this->assertSame(
            1,
            VehicleFiscalCharacteristics::query()
                ->where('vehicle_id', $vehicle->id)
                ->count(),
        );
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function update_avec_changement_fiscal_sans_metadonnees_renvoie_un_toast_erreur(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'seats_count' => 5,
        ]);

        // Changement fiscal (seats 5 → 9) mais pas de métadonnées.
        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'seats_count' => 9,
            'effective_from' => null,
            'change_reason' => null,
        ]);

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}/edit")
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}/edit")
            ->assertSessionHas('toast-error');

        // VFC courante intacte (rollback transactionnel).
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'seats_count' => 5,
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function update_avec_cascade_supprime_les_versions_posterieures(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $oldest = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $middle = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'seats_count' => 5,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'effective_from' => '2024-06-01',
            'change_reason' => 'recharacterization',
            'seats_count' => 11,
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        // Les versions postérieures ou égales à 2024-06-01 sont
        // supprimées : middle (2024-01-01 → 2024-12-31) et current
        // (2025-01-01 → null) ont effective_from >= 2024-06-01 ?
        // middle commence avant, donc il survit (mais voit son
        // effective_to ramené à 2024-05-31). current commence après
        // et est supprimée.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $oldest->id,
            'effective_to' => '2023-12-31',
        ]);
        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $current->id,
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $middle->id,
            'effective_to' => '2024-05-31',
        ]);

        // Nouvelle VFC active.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-01',
            'effective_to' => null,
            'seats_count' => 11,
        ]);
    }

    #[Test]
    public function update_m1_special_use_persiste_le_flag(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'M1',
            'body_type' => 'CI',
            'm1_special_use' => false,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'reception_category' => 'M1',
            'body_type' => 'CI',
            'm1_special_use' => true,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'm1_special_use' => true,
        ]);
    }

    #[Test]
    public function update_camionnette_n1_avec_2_rangs_et_transport_personnes_persiste_les_flags(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'N1',
            'body_type' => 'CTTE',
            'vehicle_user_type' => 'VU',
            'n1_passenger_transport' => false,
            'n1_removable_second_row_seat' => false,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'reception_category' => 'N1',
            'body_type' => 'CTTE',
            'vehicle_user_type' => 'VU',
            'n1_passenger_transport' => true,
            'n1_removable_second_row_seat' => true,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'n1_passenger_transport' => true,
            'n1_removable_second_row_seat' => true,
        ]);
    }

    #[Test]
    public function update_pickup_n1_skiable_persiste_le_flag(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'N1',
            'body_type' => 'BE',
            'vehicle_user_type' => 'VU',
            'seats_count' => 5,
            'n1_ski_lift_use' => false,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'reception_category' => 'N1',
            'body_type' => 'BE',
            'vehicle_user_type' => 'VU',
            'seats_count' => 5,
            'n1_ski_lift_use' => true,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'n1_ski_lift_use' => true,
        ]);
    }

    #[Test]
    public function update_handicap_access_persiste_le_flag(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'handicap_access' => false,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'handicap_access' => true,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'handicap_access' => true,
        ]);
    }

    #[Test]
    public function update_kerb_mass_seul_declenche_creation_nouvelle_vfc(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'kerb_mass' => 1300,
        ]);

        $payload = $this->buildVehicleUpdatePayload($vehicle, [
            'kerb_mass' => 1450,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}");

        // Ancienne VFC fermée + nouvelle VFC active.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_to' => '2025-05-31',
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'kerb_mass' => 1450,
        ]);
    }

    #[Test]
    public function index_ne_regresse_pas_en_n_plus_1_avec_dix_vehicules(): void
    {
        // Garde-fou anti-régression : l'Index Flotte itère sur tous les
        // véhicules et déclenche un calcul fiscal `vehicleFullYearTax` par
        // ligne. Sans le fix N+1 sur `findCurrentForVehicle` (qui exploite
        // la relation préchargée par `findAllForFleetView`), on aurait
        // ~1 query SQL supplémentaire par véhicule pour récupérer la VFC
        // courante. Ce test borne le total de queries de la requête HTTP
        // à un cap raisonnable indépendant du nombre de véhicules.
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $vehicle = Vehicle::factory()->create();
            VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Cap raisonnable : auth + load Vehicles + load VFC eager + load
        // contracts + load unavailabilities + fiscal rules + queries
        // collatérales Inertia. À 10 véhicules sans N+1 on observe
        // ~10-15 queries. On laisse une marge.
        self::assertLessThan(
            25,
            $queryCount,
            "Trop de queries SQL ({$queryCount}) sur l'Index Flotte avec 10 véhicules - possible régression N+1.",
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildVehicleUpdatePayload(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'license_plate' => $vehicle->license_plate,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'vin' => $vehicle->vin ?? '',
            'color' => $vehicle->color ?? '',
            'first_french_registration_date' => $vehicle->first_french_registration_date->format('Y-m-d'),
            'first_origin_registration_date' => $vehicle->first_origin_registration_date->format('Y-m-d'),
            'first_economic_use_date' => $vehicle->first_economic_use_date->format('Y-m-d'),
            'acquisition_date' => $vehicle->acquisition_date->format('Y-m-d'),
            'mileage_current' => $vehicle->mileage_current,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'body_type' => 'CI',
            'seats_count' => 5,
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6d_isc_fcm',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 120,
            // Defaults alignés sur VehicleFiscalCharacteristicsFactory pour
            // que hasFiscalChanges() ne détecte pas de faux positif.
            'kerb_mass' => 1300,
            'handicap_access' => false,
            'm1_special_use' => false,
            'n1_passenger_transport' => false,
            'n1_removable_second_row_seat' => false,
            'n1_ski_lift_use' => false,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ], $overrides);
    }
}
