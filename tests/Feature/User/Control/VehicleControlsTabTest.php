<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Models\ControlDefinition;
use App\Models\ControlRecipientDelta;
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
    public function un_destinataire_par_defaut_est_inclus_dans_les_destinataires_effectifs(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        ControlDefinition::factory()->create();

        ControlReminderSettings::singleton();
        ControlRecipientDelta::query()->create([
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'flotte@exemple.fr',
            'name' => 'Gestion flotte',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('effectiveRecipients', fn (mixed $recipients): bool => collect($recipients)
                        ->contains(fn (array $recipient): bool => $recipient['email'] === 'flotte@exemple.fr'))
                    ->etc()));
    }

    #[Test]
    public function le_badge_compte_les_controles_en_retard(): void
    {
        // Échéance dépassée sans exécution -> Overdue (échéance = mise en
        // circulation + 4 ans, ici il y a ~2 ans).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => now()->subYears(6)->toDateString(),
        ]);
        ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('controlsBadge.dueCount', 1)
                ->where('controlsBadge.overdueCount', 1)
                ->etc());
    }

    #[Test]
    public function le_badge_compte_les_controles_a_echeance_proche_sans_les_marquer_en_retard(): void
    {
        // Échéance dans 10 jours, donc dans la fenêtre de rappel (15 j) -> DueSoon.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => now()->subYears(4)->addDays(10)->toDateString(),
        ]);
        ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('controlsBadge.dueCount', 1)
                ->where('controlsBadge.overdueCount', 0)
                ->etc());
    }

    #[Test]
    public function le_badge_est_a_zero_quand_aucun_controle_n_est_du(): void
    {
        // Échéance dans ~3 ans -> Upcoming, hors fenêtre de rappel. Les 4 dates
        // sont alignées (l'ancre récente doit rester <= les autres dates
        // d'immatriculation, cf. chk_vehicles_registration_dates_ordered).
        $user = User::factory()->create();
        $recent = now()->subYear()->toDateString();
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => $recent,
            'first_french_registration_date' => $recent,
            'first_economic_use_date' => $recent,
            'acquisition_date' => $recent,
        ]);
        ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('controlsBadge.dueCount', 0)
                ->where('controlsBadge.overdueCount', 0)
                ->etc());
    }

    #[Test]
    public function le_badge_compte_un_controle_a_faire_aujourd_hui(): void
    {
        // Échéance pile aujourd'hui (mise en circulation il y a 4 ans) -> DueToday,
        // compté dans dueCount, mais pas en retard.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => now()->subYears(4)->toDateString(),
        ]);
        ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('controlsBadge.dueCount', 1)
                ->where('controlsBadge.overdueCount', 0)
                ->etc());
    }

    #[Test]
    public function le_badge_ne_compte_pas_un_controle_dont_l_echeance_tombe_le_jour_de_la_sortie(): void
    {
        // Régression : véhicule sorti aujourd'hui, échéance aujourd'hui -> le
        // contrôle n'est plus à faire (NotApplicable), badge à 0.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => now()->subYears(4)->toDateString(),
            'exit_date' => now()->toDateString(),
            'exit_reason' => 'sold',
        ]);
        ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('controlsBadge.dueCount', 0)
                ->where('controlsBadge.overdueCount', 0)
                ->etc());
    }

    #[Test]
    public function le_badge_ignore_un_vehicule_sorti_de_flotte(): void
    {
        // Échéance (~aujourd'hui) postérieure à la sortie -> NotApplicable
        // (ADR-0018), donc non comptée même si elle serait due en flotte.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => now()->subYears(4)->toDateString(),
            'exit_date' => now()->subMonths(2)->toDateString(),
            'exit_reason' => 'sold',
        ]);
        ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('controlsBadge.dueCount', 0)
                ->where('controlsBadge.overdueCount', 0)
                ->etc());
    }

    #[Test]
    public function utilisateur_non_authentifie_redirige_vers_login(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->get("/app/vehicles/{$vehicle->id}?tab=controls")->assertRedirect('/login');
    }
}
