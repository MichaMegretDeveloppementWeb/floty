<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleEventNature;
use App\Support\VehicleEvent\EventNatureCatalog;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * « Ajouter à la liste » : une saisie libre devient une suggestion non
 * réductrice du catalogue ; idempotent (insensible casse) et incapable de
 * dupliquer ou rétrograder le bloc réducteur figé.
 */
final class VehicleEventNatureControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleEventNatureSeeder::class);
        $this->user = User::factory()->create();
    }

    #[Test]
    public function persiste_une_nature_libre_comme_suggestion_non_reductrice(): void
    {
        $this->actingAs($this->user)
            ->post('/app/vehicle-event-natures', ['label' => '  Carrosserie  '])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $nature = VehicleEventNature::query()->where('label', 'Carrosserie')->first();

        $this->assertNotNull($nature);
        $this->assertFalse($nature->is_fiscally_reductive);
    }

    #[Test]
    public function un_doublon_insensible_casse_ne_cree_rien(): void
    {
        $before = VehicleEventNature::query()->count();

        $this->actingAs($this->user)
            ->post('/app/vehicle-event-natures', ['label' => 'ENTRETIEN'])
            ->assertRedirect();

        $this->assertSame($before, VehicleEventNature::query()->count());
    }

    #[Test]
    public function un_label_du_bloc_reducteur_n_est_ni_duplique_ni_retrograde(): void
    {
        $reductiveLabel = EventNatureCatalog::REDUCTIVE[0];
        $before = VehicleEventNature::query()->count();

        $this->actingAs($this->user)
            ->post('/app/vehicle-event-natures', ['label' => mb_strtolower($reductiveLabel)])
            ->assertRedirect();

        $this->assertSame($before, VehicleEventNature::query()->count());
        $this->assertTrue(
            VehicleEventNature::query()->where('label', $reductiveLabel)->firstOrFail()->is_fiscally_reductive,
        );
    }

    #[Test]
    public function valide_le_label(): void
    {
        $this->actingAs($this->user)
            ->post('/app/vehicle-event-natures', ['label' => ''])
            ->assertSessionHasErrors(['label']);

        $this->actingAs($this->user)
            ->post('/app/vehicle-event-natures', ['label' => str_repeat('a', 61)])
            ->assertSessionHasErrors(['label']);
    }

    #[Test]
    public function refuse_les_invites(): void
    {
        $this->post('/app/vehicle-event-natures', ['label' => 'Carrosserie'])
            ->assertRedirect('/login');

        $this->assertNull(VehicleEventNature::query()->where('label', 'Carrosserie')->first());
    }

    #[Test]
    public function supprime_une_suggestion_utilisateur(): void
    {
        $custom = VehicleEventNature::factory()->create(['label' => 'Carrosserie']);

        $this->actingAs($this->user)
            ->delete("/app/vehicle-event-natures/{$custom->id}")
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseMissing('vehicle_event_natures', ['id' => $custom->id]);
    }

    #[Test]
    public function supprime_une_nature_du_catalogue_de_base_non_reductrice(): void
    {
        // Seul le bloc réducteur est obligatoire ; un re-seed recrée la base.
        $base = VehicleEventNature::query()
            ->where('label', EventNatureCatalog::NON_REDUCTIVE[0])
            ->firstOrFail();

        $this->actingAs($this->user)
            ->delete("/app/vehicle-event-natures/{$base->id}")
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseMissing('vehicle_event_natures', ['id' => $base->id]);
    }

    #[Test]
    public function refuse_de_supprimer_une_nature_du_bloc_reducteur(): void
    {
        $reductive = VehicleEventNature::query()
            ->where('label', EventNatureCatalog::REDUCTIVE[0])
            ->firstOrFail();

        $this->actingAs($this->user)
            ->delete("/app/vehicle-event-natures/{$reductive->id}")
            ->assertRedirect()
            ->assertSessionHas('toast-error');

        $this->assertDatabaseHas('vehicle_event_natures', ['id' => $reductive->id]);
    }

    #[Test]
    public function la_suppression_d_une_suggestion_ne_touche_pas_les_evenements(): void
    {
        $custom = VehicleEventNature::factory()->create(['label' => 'Carrosserie']);
        $event = VehicleEvent::factory()->maintenance()
            ->withCategories('Carrosserie')
            ->create();

        $this->actingAs($this->user)
            ->delete("/app/vehicle-event-natures/{$custom->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_event_categories', [
            'vehicle_event_id' => $event->id,
            'category' => 'Carrosserie',
        ]);
    }

    #[Test]
    public function les_pages_formulaire_exposent_les_suggestions_supprimables_avec_id(): void
    {
        // Le jeu supprimable = toutes les non réductrices (base + ajouts),
        // jamais le bloc réducteur.
        $custom = VehicleEventNature::factory()->create(['label' => 'Carrosserie']);
        $vehicle = Vehicle::factory()->create();

        $expectedCount = count(EventNatureCatalog::NON_REDUCTIVE) + 1;

        $this->actingAs($this->user)
            ->get("/app/vehicles/{$vehicle->id}/events/create")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('natureSuggestions.deletable', $expectedCount)
                // Liste alphabétique : « Carrosserie » arrive après « Administratif ».
                ->where('natureSuggestions.deletable.1.id', $custom->id)
                ->where('natureSuggestions.deletable.1.label', 'Carrosserie'),
            );
    }
}
