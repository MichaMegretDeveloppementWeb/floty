<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

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
    public function index_liste_les_vehicules_avec_taxe_annuelle(): void
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
                    ->has('annualTaxDue')
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
                    ->has('euroStandards')
                    ->has('homologationMethods')
                    ->has('pollutantCategories')),
            );
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
            'pollutant_category' => 'category_1',
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
}
