<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Models\ControlDefinition;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature des surcharges / contrôles spécifiques par véhicule (Chantier B / B2).
 */
final class VehicleControlOverrideTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_surcharge_un_controle_global_avec_recette_personnalisee(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", [
                'control_definition_id' => $definition->id,
                'status' => 'active',
                'customize_schedule' => true,
                'name' => 'CT renforcé',
                'anchor' => 'first_french_registration',
                'initial_duration_value' => 3,
                'initial_duration_unit' => 'years',
                'cycle_value' => 1,
                'cycle_unit' => 'years',
                'customize_behaviour' => false,
                'customize_reminders' => false,
                'own_recipients' => [],
                'excluded_default_emails' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseHas('vehicle_control_overrides', [
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'name' => 'CT renforcé',
            'anchor' => 'first_french_registration',
            'initial_duration_value' => 3,
            // Comportement non personnalisé -> hérite (NULL).
            'notify_driver' => null,
        ]);
    }

    #[Test]
    public function store_controle_specifique_requiert_une_recette_complete(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Spécifique (pas de control_definition_id) sans nom -> rejeté.
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", [
                'control_definition_id' => null,
                'status' => 'active',
                'customize_schedule' => false,
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
                'customize_schedule' => true,
                'name' => 'Contrôle hydraulique',
                'anchor' => 'acquisition',
                'initial_duration_value' => 2,
                'initial_duration_unit' => 'years',
                'cycle_value' => 1,
                'cycle_unit' => 'years',
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
        $definition = ControlDefinition::factory()->create();

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

        // Réactivation : réutilise la même ligne (pas de doublon).
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
        $definition = ControlDefinition::factory()->create();
        $override = VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $this->actingAs($user)
            ->delete("/app/vehicles/{$vehicle->id}/controls/overrides/{$override->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('vehicle_control_overrides', ['id' => $override->id]);
    }

    #[Test]
    public function store_persiste_les_destinataires_niveau_2(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/overrides", [
                'control_definition_id' => $definition->id,
                'status' => 'active',
                'customize_schedule' => false,
                'customize_behaviour' => false,
                'customize_reminders' => false,
                'own_recipients' => [
                    ['name' => 'Garage', 'email' => 'Garage@Exemple.FR'],
                ],
                'excluded_default_emails' => ['ancien@exemple.fr'],
            ])
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
