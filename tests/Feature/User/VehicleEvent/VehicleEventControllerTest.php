<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleEventControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_cree_une_indisponibilite_avec_impact_fiscal_si_fourriere(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'pound_public',
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',
                'description' => 'Mise en fourrière suite à infraction stationnement',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'type' => 'pound_public',
            'has_fiscal_impact' => true,
        ]);
    }

    #[Test]
    public function store_ne_definit_pas_l_impact_fiscal_pour_les_autres_types(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'maintenance',
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-03',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
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
        $u = VehicleEvent::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleEventType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-10',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$u->id}", [
                'type' => 'maintenance',
                'start_date' => '2024-05-01',
                'end_date' => '2024-05-20',
                'description' => 'Prolongée',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
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
        $u = VehicleEvent::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleEventType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-05',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$u->id}", [
                'type' => 'pound_public',
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-05',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $u->id,
            'type' => 'pound_public',
            'has_fiscal_impact' => true,
        ]);
    }

    #[Test]
    public function destroy_soft_delete_une_indisponibilite(): void
    {
        $user = User::factory()->create();
        $u = VehicleEvent::factory()->create();

        $this->actingAs($user)
            ->delete("/app/vehicle-events/{$u->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('vehicle_events', ['id' => $u->id]);
    }

    #[Test]
    public function store_persiste_indispo_meme_si_la_plage_chevauche_un_contrat(): void
    {
        // ADR-0019 D1-D2 : la cohabitation indispo↔contrat est
        // autorisée. R-2024-008 traite l'intersection au moment du
        // calcul fiscal, pas au moment de la saisie.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-07-08',
            'end_date' => '2024-07-12',
        ]);

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'maintenance',
                'start_date' => '2024-07-10',
                'end_date' => '2024-07-15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-07-10',
            'end_date' => '2024-07-15',
        ]);
    }

    #[Test]
    public function show_du_vehicule_se_rend_avec_une_indispo_active_dans_l_annee(): void
    {
        // Régression : `findOverlappingWeeksForVehicle` itérait jour
        // par jour avec `$cursor->addDay()` sur un CarbonImmutable -
        // boucle infinie dès qu'une indispo couvrait des jours de
        // l'année active.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);
        $year = 2024;

        VehicleEvent::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleEventType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => sprintf('%d-03-01', $year),
            'end_date' => sprintf('%d-03-15', $year),
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk();
    }

    #[Test]
    public function update_persiste_indispo_meme_si_la_nouvelle_plage_chevauche_un_contrat(): void
    {
        // ADR-0019 D2 - symétrie create/update : un élargissement de
        // plage qui fait désormais chevaucher un contrat existant doit
        // être accepté.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $vehicleEvent = VehicleEvent::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleEventType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2024-08-01',
            'end_date' => '2024-08-05',
        ]);

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-08-12',
            'end_date' => '2024-08-20',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$vehicleEvent->id}", [
                'type' => 'maintenance',
                'start_date' => '2024-08-01',
                'end_date' => '2024-08-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $vehicleEvent->id,
            'end_date' => '2024-08-20',
        ]);
    }

    #[Test]
    public function store_cree_un_evenement_autre_avec_titre_categories_et_indispo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'other',
                'title' => 'Pose covering publicitaire',
                'categories' => ['Marketing', 'Esthétique'],
                'implies_unavailability' => true,
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertRedirect();

        $event = VehicleEvent::query()->where('vehicle_id', $vehicle->id)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('vehicle_events', [
            'id' => $event->id,
            'type' => 'other',
            'title' => 'Pose covering publicitaire',
            'has_fiscal_impact' => false,
            'implies_unavailability' => true,
        ]);
        $this->assertSame(['Marketing', 'Esthétique'], $event->categories()->pluck('category')->all());
    }

    #[Test]
    public function store_evenement_autre_sans_titre_ni_categorie_est_invalide(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'other',
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertSessionHasErrors(['title', 'categories']);
    }

    #[Test]
    public function store_evenement_autre_sans_indispo_persiste_implies_false(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'other',
                'title' => 'Note interne',
                'categories' => ['Divers'],
                'implies_unavailability' => false,
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'type' => 'other',
            'implies_unavailability' => false,
        ]);
    }

    #[Test]
    public function store_attache_les_justificatifs_joints_a_la_creation(): void
    {
        // Flux atomique A2 : les fichiers en attente voyagent avec la requête
        // de création (multipart) et sont attachés au nouvel événement.
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'maintenance',
                'start_date' => '2024-10-01',
                'end_date' => '2024-10-03',
                'documents' => [
                    UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ])
            ->assertRedirect();

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertCount(2, $event->documents()->get());
    }

    #[Test]
    public function show_rend_la_page_detail_de_l_evenement(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $event = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-05',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}/events/{$event->id}")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('User/VehicleEvents/Show/Index')
                    ->where('vehicleEvent.id', $event->id)
                    ->where('vehicle.id', $vehicle->id),
            );
    }

    #[Test]
    public function show_renvoie_404_si_l_evenement_n_appartient_pas_au_vehicule(): void
    {
        $user = User::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();
        $event = VehicleEvent::factory()->maintenance()->create(['vehicle_id' => $vehicleA->id]);

        // The event exists but belongs to another vehicle: the scoped lookup
        // throws ModelNotFound; on an HTML GET the app soft-fails to a redirect
        // (gestion-erreurs: lecture GET → redirect), never rendering the event.
        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicleB->id}/events/{$event->id}")
            ->assertRedirect();
    }

    #[Test]
    public function update_redirige_vers_la_page_detail(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $event = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-10',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$event->id}", [
                'type' => 'maintenance',
                'start_date' => '2024-05-01',
                'end_date' => '2024-05-12',
            ])
            ->assertRedirect(route('user.vehicles.events.show', [
                'vehicle' => $vehicle->id,
                'vehicleEvent' => $event->id,
            ]));
    }

    #[Test]
    public function store_rejette_un_justificatif_au_format_invalide(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'type' => 'maintenance',
                'start_date' => '2024-10-01',
                'end_date' => '2024-10-03',
                'documents' => [
                    UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
                ],
            ])
            ->assertSessionHasErrors(['documents.0']);
    }
}
