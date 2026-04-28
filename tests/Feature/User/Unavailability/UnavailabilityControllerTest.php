<?php

declare(strict_types=1);

namespace Tests\Feature\User\Unavailability;

use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Unavailability;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UnavailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_cree_une_indisponibilite_avec_impact_fiscal_si_fourriere(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/unavailabilities', [
                'vehicle_id' => $vehicle->id,
                'type' => 'pound',
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',
                'description' => 'Mise en fourrière suite à infraction stationnement',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unavailabilities', [
            'vehicle_id' => $vehicle->id,
            'type' => 'pound',
            'has_fiscal_impact' => true,
        ]);
    }

    #[Test]
    public function store_ne_definit_pas_l_impact_fiscal_pour_les_autres_types(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/unavailabilities', [
                'vehicle_id' => $vehicle->id,
                'type' => 'maintenance',
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-03',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unavailabilities', [
            'vehicle_id' => $vehicle->id,
            'type' => 'maintenance',
            'has_fiscal_impact' => false,
        ]);
    }

    #[Test]
    public function update_modifie_une_indisponibilite_existante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $u = Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-10',
        ]);

        $this->actingAs($user)
            ->patch("/app/unavailabilities/{$u->id}", [
                'type' => 'maintenance',
                'start_date' => '2024-05-01',
                'end_date' => '2024-05-20',
                'description' => 'Prolongée',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unavailabilities', [
            'id' => $u->id,
            'end_date' => '2024-05-20',
            'description' => 'Prolongée',
        ]);
    }

    #[Test]
    public function update_recalcule_l_impact_fiscal_si_type_change(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $u = Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-05',
        ]);

        $this->actingAs($user)
            ->patch("/app/unavailabilities/{$u->id}", [
                'type' => 'pound',
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-05',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unavailabilities', [
            'id' => $u->id,
            'type' => 'pound',
            'has_fiscal_impact' => true,
        ]);
    }

    #[Test]
    public function destroy_soft_delete_une_indisponibilite(): void
    {
        $user = User::factory()->create();
        $u = Unavailability::factory()->create();

        $this->actingAs($user)
            ->delete("/app/unavailabilities/{$u->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('unavailabilities', ['id' => $u->id]);
    }

    #[Test]
    public function store_refuse_si_overlap_avec_une_attribution_existante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => '2024-07-10',
        ]);

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->post('/app/unavailabilities', [
                'vehicle_id' => $vehicle->id,
                'type' => 'maintenance',
                'start_date' => '2024-07-08',
                'end_date' => '2024-07-12',
            ])
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        $this->assertDatabaseMissing('unavailabilities', [
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-07-08',
        ]);
    }

    #[Test]
    public function show_du_vehicule_se_rend_avec_une_indispo_active_dans_l_annee(): void
    {
        // Régression : `findOverlappingWeeksForVehicle` itérait jour
        // par jour avec `$cursor->addDay()` sur un CarbonImmutable —
        // boucle infinie dès qu'une indispo couvrait des jours de
        // l'année active.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);
        $year = (int) config('floty.fiscal.available_years')[0];

        Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => sprintf('%d-03-01', $year),
            'end_date' => sprintf('%d-03-15', $year),
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk();
    }

    #[Test]
    public function update_refuse_si_overlap_avec_une_attribution_existante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $unavailability = Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2024-08-01',
            'end_date' => '2024-08-05',
        ]);

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => '2024-08-15',
        ]);

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->patch("/app/unavailabilities/{$unavailability->id}", [
                'type' => 'maintenance',
                'start_date' => '2024-08-01',
                'end_date' => '2024-08-20',
            ])
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        $this->assertDatabaseHas('unavailabilities', [
            'id' => $unavailability->id,
            'end_date' => '2024-08-05',
        ]);
    }
}
