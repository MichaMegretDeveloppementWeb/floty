<?php

declare(strict_types=1);

namespace Tests\Feature\User\Settings;

use App\Models\BillingSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de la page Paramètres > Facturation (Phase 14.G V1.2).
 */
final class BillingSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_renvoie_les_settings_existants_ou_vides_si_premiere_visite(): void
    {
        $user = User::factory()->create();

        // Première visite : la table peut être vide, le singleton crée
        // automatiquement la ligne avec des champs nullables.
        $this->actingAs($user)
            ->get('/app/settings/billing')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Settings/Billing/Index')
                ->has('settings', fn (AssertableInertia $s) => $s
                    ->where('name', null)
                    ->where('siren', null)
                    ->etc()));
    }

    #[Test]
    public function update_persiste_les_valeurs_et_retourne_un_toast_success(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/billing', [
                'name' => 'Floty Location SAS',
                'address_line_1' => '12 rue du Test',
                'postal_code' => '75001',
                'city' => 'Paris',
                'siren' => '123456789',
                'contact_email' => 'contact@floty.fr',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('billing_settings', [
            'name' => 'Floty Location SAS',
            'siren' => '123456789',
            'contact_email' => 'contact@floty.fr',
        ]);

        // Singleton : une seule ligne en base après l'update.
        $this->assertSame(1, BillingSettings::query()->count());
    }

    #[Test]
    public function update_avec_email_invalide_retourne_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/billing', [
                'contact_email' => 'pas-un-email',
            ])
            ->assertSessionHasErrors(['contact_email']);
    }

    #[Test]
    public function update_idempotent_ne_cree_pas_de_doublon(): void
    {
        $user = User::factory()->create();

        // 3 mises à jour successives → toujours 1 ligne en base.
        $this->actingAs($user)
            ->post('/app/settings/billing', ['name' => 'A'])
            ->assertRedirect();
        $this->actingAs($user)
            ->post('/app/settings/billing', ['name' => 'B'])
            ->assertRedirect();
        $this->actingAs($user)
            ->post('/app/settings/billing', ['name' => 'C'])
            ->assertRedirect();

        $this->assertSame(1, BillingSettings::query()->count());
        $this->assertSame('C', BillingSettings::singleton()->name);
    }
}
