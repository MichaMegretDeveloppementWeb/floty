<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Models\ControlDefinition;
use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderSettings;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Control\EffectiveControlResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature du catalogue de contrôles réglementaires globaux
 * (Chantier B / B1, domaine B). CRUD + deltas destinataires niveau 1.
 */
final class ControlDefinitionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Contrôle technique',
            'anchor' => 'first_origin_registration',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
            'notify_driver' => false,
            'implies_unavailability' => true,
            'is_active' => true,
            'customize_reminders' => false,
            'reminder_days_before' => null,
            'reminder_on_due_day' => null,
            'reminder_repeat_every_days' => null,
            'own_recipients' => [],
            'excluded_default_emails' => [],
        ], $overrides);
    }

    #[Test]
    public function index_rend_le_catalogue_avec_le_contexte_de_rappel(): void
    {
        $user = User::factory()->create();
        ControlDefinition::factory()->create(['name' => 'Contrôle pollution']);

        $this->actingAs($user)
            ->get('/app/controls')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Controls/Index/Index')
                ->has('controls', 1)
                ->has('reminderSettings')
                ->has('anchorOptions', 4)
                ->has('durationUnitOptions', 2));
    }

    #[Test]
    public function store_cree_une_definition(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('control_definitions', [
            'name' => 'Contrôle technique',
            'anchor' => 'first_origin_registration',
            'initial_duration_value' => 4,
            'cycle_value' => 2,
            'implies_unavailability' => true,
            'reminder_days_before' => null,
        ]);
    }

    #[Test]
    public function store_persiste_les_destinataires_niveau_1_includes_et_excludes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload([
                'own_recipients' => [
                    ['name' => 'Atelier', 'email' => 'Atelier@Exemple.FR'],
                ],
                'excluded_default_emails' => ['direction@exemple.fr'],
            ]))
            ->assertRedirect();

        $definition = ControlDefinition::query()->firstOrFail();

        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'include',
            'email' => 'atelier@exemple.fr',
            'name' => 'Atelier',
        ]);
        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'exclude',
            'email' => 'direction@exemple.fr',
        ]);
    }

    #[Test]
    public function store_avec_personnalisation_des_rappels_persiste_le_cycle_propre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload([
                'customize_reminders' => true,
                'reminder_days_before' => 45,
                'reminder_on_due_day' => true,
                'reminder_repeat_every_days' => 7,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('control_definitions', [
            'reminder_days_before' => 45,
            'reminder_on_due_day' => true,
            'reminder_repeat_every_days' => 7,
        ]);
    }

    #[Test]
    public function store_rejette_la_personnalisation_des_rappels_incomplete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload([
                'customize_reminders' => true,
                'reminder_days_before' => null,
                'reminder_on_due_day' => null,
                'reminder_repeat_every_days' => null,
            ]))
            ->assertSessionHasErrors([
                'reminder_days_before',
                'reminder_on_due_day',
                'reminder_repeat_every_days',
            ]);
    }

    #[Test]
    public function store_rejette_un_nom_vide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload(['name' => '']))
            ->assertSessionHasErrors(['name']);
    }

    #[Test]
    public function store_rejette_une_duree_initiale_nulle(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload(['initial_duration_value' => 0]))
            ->assertSessionHasErrors(['initial_duration_value']);
    }

    #[Test]
    public function update_modifie_la_definition_et_resync_les_destinataires(): void
    {
        $user = User::factory()->create();
        $definition = ControlDefinition::factory()->create();
        ControlRecipientDelta::query()->create([
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'include',
            'email' => 'ancien@exemple.fr',
            'name' => 'Ancien',
        ]);

        $this->actingAs($user)
            ->patch('/app/controls/'.$definition->id, $this->validPayload([
                'name' => 'Contrôle pollution',
                'own_recipients' => [['name' => 'Nouveau', 'email' => 'nouveau@exemple.fr']],
            ]))
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('control_definitions', [
            'id' => $definition->id,
            'name' => 'Contrôle pollution',
        ]);
        $this->assertDatabaseHas('control_recipient_deltas', ['email' => 'nouveau@exemple.fr']);
        $this->assertDatabaseMissing('control_recipient_deltas', ['email' => 'ancien@exemple.fr']);
    }

    #[Test]
    public function un_destinataire_par_defaut_peut_etre_exclu_d_un_controle(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        ControlReminderSettings::singleton();
        ControlRecipientDelta::query()->create([
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'flotte@exemple.fr',
            'name' => 'Gestion flotte',
        ]);

        $this->actingAs($user)
            ->post('/app/controls', $this->validPayload([
                'excluded_default_emails' => ['flotte@exemple.fr'],
            ]))
            ->assertRedirect();

        $definition = ControlDefinition::query()->firstOrFail();

        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'exclude',
            'email' => 'flotte@exemple.fr',
        ]);

        // Le destinataire par défaut exclu ne figure plus dans les
        // destinataires effectifs résolus pour un véhicule.
        $controls = app(EffectiveControlResolver::class)->resolve($vehicle->fresh(), CarbonImmutable::parse('2026-06-05'));
        $control = collect($controls)->firstWhere('definitionId', $definition->id);
        $emails = collect($control->effectiveRecipients)->pluck('email')->all();

        self::assertNotContains('flotte@exemple.fr', $emails);
    }

    #[Test]
    public function destroy_soft_delete_la_definition(): void
    {
        $user = User::factory()->create();
        $definition = ControlDefinition::factory()->create();

        $this->actingAs($user)
            ->delete('/app/controls/'.$definition->id)
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertSoftDeleted('control_definitions', ['id' => $definition->id]);
    }

    #[Test]
    public function utilisateur_non_authentifie_redirige_vers_login(): void
    {
        $this->get('/app/controls')->assertRedirect('/login');
    }
}
