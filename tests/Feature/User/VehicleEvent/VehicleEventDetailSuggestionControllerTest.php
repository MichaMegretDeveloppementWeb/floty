<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDetailSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catalogue des suggestions de la section « Détails » : entièrement géré par
 * l'utilisateur (ajout idempotent insensible casse, retrait libre), sans
 * impact sur les lignes déjà attachées aux événements.
 */
final class VehicleEventDetailSuggestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function persiste_un_detail_comme_suggestion(): void
    {
        $this->actingAs($this->user)
            ->post('/app/vehicle-event-detail-suggestions', ['label' => '  Vidange  '])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('vehicle_event_detail_suggestions', ['label' => 'Vidange']);
    }

    #[Test]
    public function un_doublon_insensible_casse_ne_cree_rien(): void
    {
        VehicleEventDetailSuggestion::factory()->create(['label' => 'Vidange']);

        $this->actingAs($this->user)
            ->post('/app/vehicle-event-detail-suggestions', ['label' => 'VIDANGE'])
            ->assertRedirect();

        $this->assertSame(1, VehicleEventDetailSuggestion::query()->count());
    }

    #[Test]
    public function valide_le_label(): void
    {
        $this->actingAs($this->user)
            ->post('/app/vehicle-event-detail-suggestions', ['label' => ''])
            ->assertSessionHasErrors(['label']);

        $this->actingAs($this->user)
            ->post('/app/vehicle-event-detail-suggestions', ['label' => str_repeat('a', 101)])
            ->assertSessionHasErrors(['label']);
    }

    #[Test]
    public function supprime_une_suggestion(): void
    {
        $suggestion = VehicleEventDetailSuggestion::factory()->create(['label' => 'Vidange']);

        $this->actingAs($this->user)
            ->delete("/app/vehicle-event-detail-suggestions/{$suggestion->id}")
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseMissing('vehicle_event_detail_suggestions', ['id' => $suggestion->id]);
    }

    #[Test]
    public function la_suppression_d_une_suggestion_ne_touche_pas_les_evenements(): void
    {
        $suggestion = VehicleEventDetailSuggestion::factory()->create(['label' => 'Vidange']);
        $event = VehicleEvent::factory()->maintenance()->create();
        $event->details()->create(['detail' => 'Vidange']);

        $this->actingAs($this->user)
            ->delete("/app/vehicle-event-detail-suggestions/{$suggestion->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_event_details', [
            'vehicle_event_id' => $event->id,
            'detail' => 'Vidange',
        ]);
    }

    #[Test]
    public function refuse_les_invites(): void
    {
        $this->post('/app/vehicle-event-detail-suggestions', ['label' => 'Vidange'])
            ->assertRedirect('/login');

        $this->assertNull(VehicleEventDetailSuggestion::query()->where('label', 'Vidange')->first());
    }

    #[Test]
    public function les_pages_formulaire_exposent_les_suggestions_de_details(): void
    {
        $suggestion = VehicleEventDetailSuggestion::factory()->create(['label' => 'Vidange']);
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->user)
            ->get("/app/vehicles/{$vehicle->id}/events/create")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('detailSuggestions', 1)
                ->where('detailSuggestions.0.id', $suggestion->id)
                ->where('detailSuggestions.0.label', 'Vidange'),
            );
    }
}
