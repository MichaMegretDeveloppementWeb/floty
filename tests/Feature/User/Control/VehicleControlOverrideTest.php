<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Models\ControlDefinition;
use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderSettings;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\EffectiveControlResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature des surcharges / contrôles spécifiques par véhicule (Chantier B).
 * Une surcharge est un formulaire complet prérempli : le serveur ne stocke que
 * ce qui DIFFÈRE du contrôle global (sinon NULL = hérite), n'enregistre jamais le
 * nom, et ne matérialise pas une surcharge inerte.
 */
final class VehicleControlOverrideTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function definition(array $overrides = []): ControlDefinition
    {
        return ControlDefinition::factory()->create(array_merge([
            'name' => 'Contrôle technique',
            'anchor' => 'first_origin_registration',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
            'notify_driver' => false,
            'implies_unavailability' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function overridePayload(int $definitionId, array $overrides = []): array
    {
        // Defaults mirror the definition above, so an unchanged payload is inert.
        return array_merge([
            'control_definition_id' => $definitionId,
            'status' => 'active',
            'anchor' => 'first_origin_registration',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
            'notify_driver' => false,
            'implies_unavailability' => false,
            'customize_reminders' => false,
            'own_recipients' => [],
            'excluded_default_emails' => [],
        ], $overrides);
    }

    #[Test]
    public function la_surcharge_ne_stocke_que_les_champs_differents_du_global(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", $this->overridePayload($definition->id, [
                'anchor' => 'first_french_registration', // diffère
                'initial_duration_value' => 3,           // diffère
                'cycle_value' => 2,                      // identique -> NULL
                'implies_unavailability' => true,        // diffère
                'notify_driver' => false,                // identique -> NULL
            ]))
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('vehicle_control_overrides', [
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'name' => null,
            'anchor' => 'first_french_registration',
            'initial_duration_value' => 3,
            'cycle_value' => null,
            'implies_unavailability' => true,
            'notify_driver' => null,
        ]);
    }

    #[Test]
    public function la_surcharge_n_enregistre_jamais_le_nom(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", $this->overridePayload($definition->id, [
                'name' => 'Tentative de renommage',
                'initial_duration_value' => 3, // un diff pour matérialiser la ligne
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_control_overrides', [
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'name' => null,
            'initial_duration_value' => 3,
        ]);
    }

    #[Test]
    public function une_surcharge_inerte_ne_cree_pas_de_ligne(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        // Recette identique au global, aucun destinataire, active.
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", $this->overridePayload($definition->id))
            ->assertRedirect();

        $this->assertDatabaseCount('vehicle_control_overrides', 0);
    }

    #[Test]
    public function une_surcharge_devenue_inerte_est_reinitialisee(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();
        $override = VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
            'cycle_value' => 1, // une personnalisation existante
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicles/{$vehicle->id}/controls/overrides/{$override->id}", $this->overridePayload($definition->id))
            ->assertRedirect();

        $this->assertSoftDeleted('vehicle_control_overrides', ['id' => $override->id]);
    }

    #[Test]
    public function is_overridden_est_vrai_des_qu_un_champ_ou_un_destinataire_differe(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        // Diff de cycle uniquement (ancre inchangée pour un calcul d'échéance sûr).
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", $this->overridePayload($definition->id, [
                'cycle_value' => 1,
            ]))
            ->assertRedirect();

        $controls = app(EffectiveControlResolver::class)->resolve($vehicle->fresh(), CarbonImmutable::parse('2026-06-05'));
        $control = collect($controls)->firstWhere('definitionId', $definition->id);

        self::assertNotNull($control);
        self::assertTrue($control->isOverridden);
    }

    #[Test]
    public function store_controle_specifique_requiert_une_recette_complete(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", [
                'control_definition_id' => null,
                'status' => 'active',
                'name' => '',
                'own_recipients' => [],
                'excluded_default_emails' => [],
            ])
            ->assertSessionHasErrors(['name', 'initial_duration_value', 'cycle_value']);
    }

    #[Test]
    public function store_controle_specifique_valide(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", [
                'control_definition_id' => null,
                'status' => 'active',
                'name' => 'Contrôle hydraulique',
                'anchor' => 'acquisition',
                'initial_duration_value' => 2,
                'initial_duration_unit' => 'years',
                'cycle_value' => 1,
                'cycle_unit' => 'years',
                'notify_driver' => false,
                'implies_unavailability' => false,
                'customize_reminders' => false,
                'own_recipients' => [],
                'excluded_default_emails' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_control_overrides', [
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => null,
            'name' => 'Contrôle hydraulique',
        ]);
    }

    #[Test]
    public function set_status_desactive_puis_reactive_un_controle_global(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/status", [
                'control_definition_id' => $definition->id,
                'status' => 'disabled',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_control_overrides', [
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'status' => 'disabled',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/status", [
                'control_definition_id' => $definition->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        self::assertSame(1, VehicleControlOverride::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('vehicle_control_overrides', [
            'control_definition_id' => $definition->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function reset_soft_delete_la_surcharge(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();
        $override = VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $this->actingAs($user)
            ->delete("/app/vehicles/{$vehicle->id}/controls/overrides/{$override->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('vehicle_control_overrides', ['id' => $override->id]);
    }

    #[Test]
    public function la_cascade_destinataires_resout_les_trois_niveaux(): void
    {
        // Exemple client (memo Chantier B §7) : Sabrina = destinataire global ;
        // le contrôle global enlève Sabrina + ajoute Hugo ; le véhicule enlève
        // Hugo + ajoute Vanessa. Patron (destinataire par défaut) reste partout.
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        // Niveau 0 : deux destinataires par défaut, Patron + Sabrina.
        ControlReminderSettings::singleton();
        ControlRecipientDelta::query()->create([
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'patron@floty.fr',
            'name' => 'Patron',
        ]);
        ControlRecipientDelta::query()->create([
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'sabrina@exemple.fr',
            'name' => 'Sabrina',
        ]);

        // Niveau 1 (contrôle global) : enlève Sabrina, ajoute Hugo.
        ControlRecipientDelta::query()->create([
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'exclude',
            'email' => 'sabrina@exemple.fr',
        ]);
        ControlRecipientDelta::query()->create([
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'include',
            'email' => 'hugo@exemple.fr',
            'name' => 'Hugo',
        ]);

        // Niveau 2 (véhicule) : enlève Hugo, ajoute Vanessa.
        $override = VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
        ]);
        ControlRecipientDelta::query()->create([
            'level' => 'vehicle',
            'vehicle_control_override_id' => $override->id,
            'operation' => 'exclude',
            'email' => 'hugo@exemple.fr',
        ]);
        ControlRecipientDelta::query()->create([
            'level' => 'vehicle',
            'vehicle_control_override_id' => $override->id,
            'operation' => 'include',
            'email' => 'vanessa@exemple.fr',
            'name' => 'Vanessa',
        ]);

        $controls = app(EffectiveControlResolver::class)->resolve($vehicle->fresh(), CarbonImmutable::parse('2026-06-05'));
        $control = collect($controls)->firstWhere('definitionId', $definition->id);
        self::assertNotNull($control);

        // Niveau 1 résolu (hérité par le véhicule s'il ne surcharge pas) :
        // toujours-prévenu + Hugo, plus de Sabrina.
        $inherited = collect($control->inheritedRecipients)->pluck('email')->sort()->values()->all();
        self::assertSame(['hugo@exemple.fr', 'patron@floty.fr'], $inherited);

        // Niveau 2 résolu (effectif) : toujours-prévenu + Vanessa, plus de Hugo
        // ni de Sabrina.
        $effective = collect($control->effectiveRecipients)->pluck('email')->sort()->values()->all();
        self::assertSame(['patron@floty.fr', 'vanessa@exemple.fr'], $effective);
    }

    #[Test]
    public function store_persiste_les_destinataires_niveau_2(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = $this->definition();

        // Recette identique au global mais des destinataires -> la ligne existe.
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", $this->overridePayload($definition->id, [
                'own_recipients' => [
                    ['name' => 'Garage', 'email' => 'Garage@Exemple.FR'],
                ],
                'excluded_default_emails' => ['ancien@exemple.fr'],
            ]))
            ->assertRedirect();

        $override = VehicleControlOverride::query()->firstOrFail();

        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'vehicle',
            'vehicle_control_override_id' => $override->id,
            'operation' => 'include',
            'email' => 'garage@exemple.fr',
        ]);
        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'vehicle',
            'vehicle_control_override_id' => $override->id,
            'operation' => 'exclude',
            'email' => 'ancien@exemple.fr',
        ]);
    }
}
