<?php

declare(strict_types=1);

namespace Tests\Feature\User\Contract;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature CRUD Contract — couvre l'auth, les redirects, la
 * validation FR et la propagation au repo via Action+Service.
 */
final class ContractControllerTest extends TestCase
{
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
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Contracts/Index/Index')
                ->has('contracts', 2));
    }

    #[Test]
    public function index_refuse_l_acces_aux_invites(): void
    {
        $this->get('/app/contracts')->assertRedirect('/login');
    }

    #[Test]
    public function show_renvoie_le_dto_du_contrat(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',
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
                    ->etc()));
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
