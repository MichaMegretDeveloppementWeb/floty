<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_liste_les_vehicules_avec_cout_plein_annee_et_taux_journalier(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Index/Index')
                ->has('vehicles', 1, fn (AssertableInertia $v) => $v
                    ->where('id', $vehicle->id)
                    ->where('licensePlate', $vehicle->license_plate)
                    ->has('fullYearTax')
                    ->has('dailyTaxRate')
                    ->etc()),
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
                            ->has('co2Method')
                            ->has('co2FullYearTariff')
                            ->has('co2Explanation')
                            ->has('pollutantCategory')
                            ->has('pollutantsFullYearTariff')
                            ->has('pollutantsExplanation')
                            ->has('exemptionReasons')
                            ->has('appliedRuleCodes')
                            ->has('total')
                            ->has('appliedRules')))
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
        $year = (int) config('floty.fiscal.available_years')[0];

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

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Show/Index')
                ->has('vehicle.usageStats', fn (AssertableInertia $s) => $s
                    ->where('fiscalYear', $year)
                    ->where('daysUsedThisYear', 95)
                    ->has('daysInYear')
                    ->has('actualTaxThisYear')
                    ->has('fullYearTax')
                    ->has('dailyTaxRate')
                    ->has('companies', 2)
                    ->where('companies.0.shortCode', 'BETA')
                    ->where('companies.0.daysUsed', 60)
                    ->has('companies.0.proratoPercent')
                    ->has('companies.0.taxCo2')
                    ->has('companies.0.taxPollutants')
                    ->has('companies.0.taxTotal')
                    ->where('companies.1.shortCode', 'ALPH')
                    ->where('companies.1.daysUsed', 35)
                    ->has('weeklyBreakdown')
                    ->has('fullYearTaxBreakdown')),
            );
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
            'first_french_registration_date' => $vehicle->first_french_registration_date?->format('Y-m-d'),
            'first_origin_registration_date' => $vehicle->first_origin_registration_date?->format('Y-m-d'),
            'first_economic_use_date' => $vehicle->first_economic_use_date?->format('Y-m-d'),
            'acquisition_date' => $vehicle->acquisition_date?->format('Y-m-d'),
            'mileage_current' => $vehicle->mileage_current,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'body_type' => 'CI',
            'seats_count' => 5,
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6d_isc_fcm',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 120,
            'effective_from' => '2025-06-01',
            'change_reason' => 'recharacterization',
        ], $overrides);
    }
}
