<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Models\ControlDefinition;
use App\Models\ControlReminderSettings;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de l'onglet « Contrôles » d'un véhicule (Chantier B / B2) :
 * résolution des contrôles effectifs (global, surcharge, désactivé, spécifique)
 * et cascade des destinataires.
 */
final class VehicleControlsTabTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function l_onglet_expose_un_controle_global_effectif(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['first_origin_registration_date' => '2020-01-01']);
        $definition = ControlDefinition::factory()->create([
            'name' => 'Contrôle technique',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Show/Index')
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('definitionId', $definition->id)
                    ->where('isVehicleSpecific', false)
                    ->where('name', 'Contrôle technique')
                    ->where('nextDueDate', '2024-01-01')
                    ->etc()));
    }

    #[Test]
    public function la_surcharge_remplace_le_nom_et_marque_le_controle_comme_surcharge(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create(['name' => 'Contrôle technique']);
        VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
            'name' => 'CT renforcé',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('name', 'CT renforcé')
                    ->where('isOverridden', true)
                    ->etc()));
    }

    #[Test]
    public function un_controle_desactive_reste_present_avec_le_statut_disabled(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();
        VehicleControlOverride::factory()->overrideOf($definition)->disabled()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('status', 'disabled')
                    ->etc()));
    }

    #[Test]
    public function un_controle_specifique_apparait(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        // No global definitions: only the vehicle-specific control.
        VehicleControlOverride::factory()->create([
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => null,
            'name' => 'Contrôle spécifique',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('isVehicleSpecific', true)
                    ->where('name', 'Contrôle spécifique')
                    ->etc()));
    }

    #[Test]
    public function l_email_toujours_prevenu_est_inclus_dans_les_destinataires_effectifs(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        ControlDefinition::factory()->create();

        $settings = ControlReminderSettings::singleton();
        $settings->always_notify_name = 'Gestion flotte';
        $settings->always_notify_email = 'flotte@exemple.fr';
        $settings->save();

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('effectiveRecipients', fn (mixed $recipients): bool => collect($recipients)
                        ->contains(fn (array $recipient): bool => $recipient['email'] === 'flotte@exemple.fr'))
                    ->etc()));
    }

    #[Test]
    public function utilisateur_non_authentifie_redirige_vers_login(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->get("/app/vehicles/{$vehicle->id}?tab=controls")->assertRedirect('/login');
    }
}
