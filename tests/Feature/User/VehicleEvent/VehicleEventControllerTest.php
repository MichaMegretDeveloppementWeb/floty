<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleEventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Catalogue des natures : requis pour que les natures réductrices
        // soient reconnues à l'écriture.
        $this->seed(VehicleEventNatureSeeder::class);
    }

    #[Test]
    public function store_cree_une_indisponibilite_avec_impact_fiscal_si_nature_fourriere(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Mise en fourrière',
                'categories' => ['Fourrière (demande publique)'],
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',
                'description' => 'Mise en fourrière suite à infraction stationnement',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'title' => 'Mise en fourrière',
            'has_fiscal_impact' => true,
        ]);
    }

    #[Test]
    public function le_code_postal_doit_etre_une_suite_de_4_a_6_chiffres(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $payload = [
            'vehicle_id' => $vehicle->id,
            'title' => 'Entretien courant',
            'categories' => ['Entretien'],
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-03',
        ];

        foreach (['91A00', '123', '1234567', '91 100'] as $invalid) {
            $this->actingAs($user)
                ->post('/app/vehicle-events', [...$payload, 'postal_code' => $invalid])
                ->assertSessionHasErrors(['postal_code']);
        }

        $this->actingAs($user)
            ->post('/app/vehicle-events', [...$payload, 'postal_code' => '91100'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', ['postal_code' => '91100']);
    }

    #[Test]
    public function un_garage_enregistre_alimente_automatiquement_les_suggestions(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // La page de creation ne propose encore aucun garage.
        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}/events/create")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('garageSuggestions', []),
            );

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-03',
                'garage' => 'Garage Martin',
                'postal_code' => '91100',
            ])
            ->assertRedirect();

        // Sans aucun geste manuel, le garage est desormais propose.
        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}/events/create")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('garageSuggestions', ['Garage Martin']),
            );
    }

    #[Test]
    public function store_ne_definit_pas_l_impact_fiscal_pour_les_natures_non_reductrices(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
                'start_date' => '2024-04-01',
                'end_date' => '2024-04-03',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'title' => 'Entretien courant',
            'has_fiscal_impact' => false,
        ]);
    }

    #[Test]
    public function update_modifie_une_indisponibilite_existante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $u = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-05-01',
            'end_date' => '2024-05-10',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$u->id}", [
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
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
    public function update_remplace_les_details_et_le_garage(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $event = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'garage' => 'Garage Martin',
            'postal_code' => '91100',
        ]);
        $event->details()->create(['detail' => 'Vidange']);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$event->id}", [
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
                'details' => ['Changement courroie', 'Contrôle des niveaux'],
                'garage' => 'Carrosserie Dupont',
                'postal_code' => '75011',
                'start_date' => $event->start_date->toDateString(),
                'end_date' => $event->end_date?->toDateString(),
            ])
            ->assertRedirect();

        // Les lignes de détail sont remplacées (l'ancienne disparaît).
        $this->assertSame(
            ['Changement courroie', 'Contrôle des niveaux'],
            $event->details()->pluck('detail')->all(),
        );
        $this->assertDatabaseHas('vehicle_events', [
            'id' => $event->id,
            'garage' => 'Carrosserie Dupont',
            'postal_code' => '75011',
        ]);

        // Vider garage / code postal / détails les remet à null / zéro ligne.
        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$event->id}", [
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
                'start_date' => $event->start_date->toDateString(),
                'end_date' => $event->end_date?->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(0, $event->details()->count());
        $this->assertDatabaseHas('vehicle_events', [
            'id' => $event->id,
            'garage' => null,
            'postal_code' => null,
        ]);
    }

    #[Test]
    public function update_recalcule_l_impact_fiscal_si_les_natures_changent(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $u = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-05',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-events/{$u->id}", [
                'title' => 'Mise en fourrière',
                'categories' => ['Fourrière (demande publique)'],
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-05',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'id' => $u->id,
            'title' => 'Mise en fourrière',
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
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
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

        VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
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

        $vehicleEvent = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
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
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
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
    public function store_cree_un_evenement_avec_titre_natures_libres_et_indispo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
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
            'title' => 'Pose covering publicitaire',
            'has_fiscal_impact' => false,
            'implies_unavailability' => true,
        ]);
        $this->assertSame(['Marketing', 'Esthétique'], $event->categories()->pluck('category')->all());
    }

    #[Test]
    public function store_sans_titre_ni_nature_est_invalide(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertSessionHasErrors([
                'title' => "Le nom de l'événement est obligatoire.",
                'categories' => 'Au moins une nature est obligatoire.',
            ]);
    }

    #[Test]
    public function store_rejette_une_nature_dupliquee_insensible_casse(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Entretien courant',
                'categories' => ['Entretien', 'entretien'],
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertSessionHasErrors([
                'categories.0' => 'Cette nature est déjà présente.',
            ]);
    }

    #[Test]
    public function store_rejette_une_nature_trop_longue(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Entretien courant',
                'categories' => [str_repeat('a', 61)],
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertSessionHasErrors([
                'categories.0' => 'Une nature ne peut pas dépasser 60 caractères.',
            ]);
    }

    #[Test]
    public function store_evenement_non_reducteur_sans_indispo_persiste_implies_false(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post('/app/vehicle-events', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Note interne',
                'categories' => ['Divers'],
                'implies_unavailability' => false,
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-02',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'title' => 'Note interne',
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
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
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
    public function create_expose_les_suggestions_de_natures_en_deux_blocs(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}/events/create")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('User/VehicleEvents/Create/Index')
                    ->has('natureSuggestions.reductive', 3)
                    ->has('natureSuggestions.other'),
            );
    }

    #[Test]
    public function edit_expose_les_suggestions_de_natures_en_deux_blocs(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $event = VehicleEvent::factory()->maintenance()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-05',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}/events/{$event->id}/edit")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('User/VehicleEvents/Edit/Index')
                    ->has('natureSuggestions.reductive', 3)
                    ->has('natureSuggestions.other'),
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
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
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
                'title' => 'Entretien courant',
                'categories' => ['Entretien'],
                'start_date' => '2024-10-01',
                'end_date' => '2024-10-03',
                'documents' => [
                    UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
                ],
            ])
            ->assertSessionHasErrors(['documents.0']);
    }
}
