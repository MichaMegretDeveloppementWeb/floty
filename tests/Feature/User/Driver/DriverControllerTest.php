<?php

declare(strict_types=1);

namespace Tests\Feature\User\Driver;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DriverControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_liste_les_drivers_avec_companies_actives_et_count_contrats(): void
    {
        $user = User::factory()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $driver = Driver::factory()->create();
        $driver->companies()->attach($company1->id, ['joined_at' => '2024-01-01', 'left_at' => null]);
        $driver->companies()->attach($company2->id, ['joined_at' => '2025-06-01', 'left_at' => null]);

        $this->actingAs($user)
            ->get('/app/drivers')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Drivers/Index/Index')
                ->has('drivers', 1, fn (AssertableInertia $d) => $d
                    ->where('id', $driver->id)
                    ->where('fullName', $driver->full_name)
                    ->where('initials', $driver->initials)
                    ->where('totalActiveCompaniesCount', 2)
                    ->has('activeCompanies', 2)
                    ->where('contractsCount', 0),
                ),
            );
    }

    #[Test]
    public function show_renvoie_le_driver_avec_memberships_et_contracts_count(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $driver = Driver::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $this->actingAs($user)
            ->get('/app/drivers/'.$driver->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Drivers/Show/Index')
                ->has('driver', fn (AssertableInertia $d) => $d
                    ->where('id', $driver->id)
                    ->where('firstName', $driver->first_name)
                    ->where('lastName', $driver->last_name)
                    ->has('memberships', 1, fn (AssertableInertia $m) => $m
                        ->where('companyId', $company->id)
                        ->where('isCurrentlyActive', true)
                        ->etc(),
                    )
                    ->where('contractsCount', 0)
                    ->etc(),
                ),
            );
    }

    #[Test]
    public function store_cree_le_driver_avec_membership_initiale(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $response = $this->actingAs($user)->post('/app/drivers', [
            'first_name' => 'Marie',
            'last_name' => 'Dupont',
            'initial_company_id' => $company->id,
            'initial_joined_at' => '2024-01-15',
        ]);

        $response->assertRedirect();

        $driver = Driver::query()->where('first_name', 'Marie')->where('last_name', 'Dupont')->firstOrFail();
        $this->assertCount(1, $driver->companies);
        $this->assertSame($company->id, $driver->companies->first()->id);
        $this->assertSame('2024-01-15', $driver->companies->first()->pivot->joined_at->toDateString());
        $this->assertNull($driver->companies->first()->pivot->left_at);
    }

    #[Test]
    public function store_refuse_la_creation_sans_company_initiale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/drivers', [
                'first_name' => 'Marie',
                'last_name' => 'Dupont',
            ])
            ->assertSessionHasErrors(['initial_company_id', 'initial_joined_at']);

        $this->assertDatabaseEmpty('drivers');
    }

    #[Test]
    public function update_modifie_uniquement_first_name_et_last_name(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create(['first_name' => 'Old', 'last_name' => 'Name']);

        $this->actingAs($user)
            ->patch('/app/drivers/'.$driver->id, [
                'first_name' => 'New',
                'last_name' => 'Name',
            ])
            ->assertRedirect();

        $driver->refresh();
        $this->assertSame('New', $driver->first_name);
    }

    #[Test]
    public function destroy_refuse_si_driver_a_des_contrats(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
        ]);

        $this->actingAs($user)
            ->delete('/app/drivers/'.$driver->id)
            ->assertRedirect();

        $this->assertNotNull(Driver::query()->find($driver->id));
    }

    #[Test]
    public function destroy_supprime_si_aucun_contrat(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create();

        $this->actingAs($user)
            ->delete('/app/drivers/'.$driver->id)
            ->assertRedirect();

        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
    }

    #[Test]
    public function attach_company_ajoute_une_membership(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/app/drivers/'.$driver->id.'/memberships', [
                'company_id' => $company->id,
                'joined_at' => '2025-01-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'joined_at' => '2025-01-01',
        ]);
    }

    #[Test]
    public function leave_company_pose_left_at_si_aucun_contrat_a_resoudre(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $driver = Driver::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $this->actingAs($user)
            ->patch('/app/drivers/'.$driver->id.'/memberships/'.$company->id.'/leave', [
                'left_at' => '2026-06-30',
                'future_contracts_resolution' => 'none',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'left_at' => '2026-06-30',
        ]);
    }

    #[Test]
    public function detach_company_refuse_si_membership_a_des_contrats(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
        ]);

        $this->actingAs($user)
            ->delete('/app/drivers/'.$driver->id.'/memberships/'.$pivotId)
            ->assertRedirect();

        $this->assertDatabaseHas('driver_company', ['id' => $pivotId]);
    }

    #[Test]
    public function leave_company_renvoie_toast_error_si_aucune_membership_active(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        // Aucune membership attachée → action doit lever DriverMembershipNotFoundException

        $response = $this->actingAs($user)
            ->patch('/app/drivers/'.$driver->id.'/memberships/'.$company->id.'/leave', [
                'left_at' => '2026-06-30',
                'future_contracts_resolution' => 'none',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('toast-error', 'Aucune appartenance active à cette entreprise.');
    }

    #[Test]
    public function leave_company_avec_mode_replace_reassigne_les_contrats_a_venir(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $remplacant = Driver::factory()->create();
        $remplacant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $contract = Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => $sortant->id,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
        ]);

        $this->actingAs($user)
            ->patch('/app/drivers/'.$sortant->id.'/memberships/'.$company->id.'/leave', [
                'left_at' => '2026-06-30',
                'future_contracts_resolution' => 'replace',
                'replacement_map' => [$contract->id => $remplacant->id],
            ])
            ->assertRedirect();

        $this->assertSame($remplacant->id, $contract->fresh()->driver_id);
        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $sortant->id,
            'company_id' => $company->id,
            'left_at' => '2026-06-30',
        ]);
    }

    #[Test]
    public function leave_company_avec_mode_detach_passe_les_contrats_a_venir_a_null(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $driver = Driver::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $contract = Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
        ]);

        $this->actingAs($user)
            ->patch('/app/drivers/'.$driver->id.'/memberships/'.$company->id.'/leave', [
                'left_at' => '2026-06-30',
                'future_contracts_resolution' => 'detach',
            ])
            ->assertRedirect();

        $this->assertNull($contract->fresh()->driver_id);
    }

    #[Test]
    public function detach_company_renvoie_toast_error_si_pivot_id_introuvable(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create();

        $response = $this->actingAs($user)
            ->delete('/app/drivers/'.$driver->id.'/memberships/99999');

        $response->assertRedirect();
        $response->assertSessionHas('toast-error', 'Appartenance introuvable.');
    }

    #[Test]
    public function contract_options_renvoie_drivers_actifs_sur_la_periode(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // Active toute la période
        $active = Driver::factory()->create(['first_name' => 'Active', 'last_name' => 'Driver']);
        $active->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        // Sorti avant la période demandée
        $left = Driver::factory()->create(['first_name' => 'Left', 'last_name' => 'Driver']);
        $left->companies()->attach($company->id, ['joined_at' => '2023-01-01', 'left_at' => '2024-12-31']);

        // Pas encore entré
        $notYet = Driver::factory()->create(['first_name' => 'NotYet', 'last_name' => 'Driver']);
        $notYet->companies()->attach($company->id, ['joined_at' => '2025-08-01', 'left_at' => null]);

        $response = $this->actingAs($user)->getJson('/app/drivers/options?'.http_build_query([
            'company_id' => $company->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ]));

        $response->assertOk();
        $drivers = $response->json('drivers');
        $this->assertCount(1, $drivers);
        $this->assertSame($active->id, $drivers[0]['id']);
    }
}
