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
                            ->has('appliedExemptions')
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
