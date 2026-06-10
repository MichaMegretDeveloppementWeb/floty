<?php

declare(strict_types=1);

namespace Tests\Feature\User\VehicleEvent;

use App\Models\User;
use App\Models\VehicleEventNature;
use App\Support\VehicleEvent\EventNatureCatalog;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->post('/app/vehicle-event-natures', ['label' => 'maintenance / ENTRETIEN'])
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
}
