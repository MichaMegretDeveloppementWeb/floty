<?php

declare(strict_types=1);

namespace Tests\Feature\User\Control;

use App\Models\ControlDefinition;
use App\Models\ControlExecution;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature du marquage « Fait » d'un contrôle par véhicule (Chantier B / B2).
 */
final class VehicleControlExecutionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function record_execution_cree_l_execution_et_genere_un_evenement(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['first_origin_registration_date' => '2020-01-01']);
        $definition = ControlDefinition::factory()->create([
            'name' => 'Contrôle technique',
            'implies_unavailability' => true,
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/executions", [
                'vehicle_id' => $vehicle->id,
                'control_definition_id' => $definition->id,
                'executed_on' => '2024-03-10',
                'note' => 'Passé au centre agréé.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $execution = ControlExecution::query()->firstOrFail();
        self::assertSame($vehicle->id, $execution->vehicle_id);
        self::assertSame($definition->id, $execution->control_definition_id);
        self::assertNotNull($execution->vehicle_event_id);

        // L'exécution génère un événement véhicule (Chantier A), catégorie Contrôle,
        // non fiscal, indisponibilité selon le flag effectif de la définition.
        // L'événement est sur UN SEUL jour (start = end = date d'exécution) : un
        // « Fait » est ponctuel, un end_date null marquerait le véhicule
        // indisponible indéfiniment (heatmap / usage / planning).
        $this->assertDatabaseHas('vehicle_events', [
            'id' => $execution->vehicle_event_id,
            'vehicle_id' => $vehicle->id,
            'type' => 'other',
            'title' => 'Contrôle technique',
            'category' => 'Contrôle',
            'has_fiscal_impact' => false,
            'implies_unavailability' => true,
            'start_date' => '2024-03-10',
            'end_date' => '2024-03-10',
        ]);
    }

    #[Test]
    public function record_execution_attache_les_documents(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/executions", [
                'vehicle_id' => $vehicle->id,
                'control_definition_id' => $definition->id,
                'executed_on' => '2024-03-10',
                'documents' => [
                    UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $execution = ControlExecution::query()->firstOrFail();
        $this->assertDatabaseHas('control_execution_documents', [
            'control_execution_id' => $execution->id,
            'filename' => 'rapport.pdf',
        ]);
    }

    #[Test]
    public function l_execution_fait_avancer_l_echeance(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['first_origin_registration_date' => '2020-01-01']);
        $definition = ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/executions", [
                'vehicle_id' => $vehicle->id,
                'control_definition_id' => $definition->id,
                'executed_on' => '2024-06-01',
            ])
            ->assertRedirect();

        // La date d'exécution devient la nouvelle référence : prochaine = exec + cycle.
        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('lastExecutionDate', '2024-06-01')
                    ->where('nextDueDate', '2026-06-01')
                    ->etc()));
    }

    #[Test]
    public function fait_en_avance_la_date_saisie_devient_la_reference(): void
    {
        // Échéance initiale au 2027-06-01 (immat. 2023-06-01 + 4 ans), mais on
        // fait le contrôle EN AVANCE le 2026-05-01 : cette date devient la
        // nouvelle référence -> prochaine = 2026-05-01 + cycle (2 ans).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['first_origin_registration_date' => '2023-06-01']);
        $definition = ControlDefinition::factory()->create([
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/executions", [
                'vehicle_id' => $vehicle->id,
                'control_definition_id' => $definition->id,
                'executed_on' => '2026-05-01',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}?tab=controls")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehicleControls.controls', 1, fn (AssertableInertia $control) => $control
                    ->where('lastExecutionDate', '2026-05-01')
                    ->where('nextDueDate', '2028-05-01')
                    ->etc()));
    }

    #[Test]
    public function record_execution_refuse_une_date_posterieure_a_la_sortie_de_flotte(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'exit_date' => '2024-01-01',
            'exit_reason' => 'sold',
        ]);
        $definition = ControlDefinition::factory()->create();

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/controls/executions", [
                'vehicle_id' => $vehicle->id,
                'control_definition_id' => $definition->id,
                'executed_on' => '2024-06-01',
            ])
            ->assertSessionHasErrors(['executed_on']);
    }

    #[Test]
    public function delete_execution_soft_delete(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();
        $execution = ControlExecution::factory()->create([
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
        ]);

        $this->actingAs($user)
            ->delete("/app/vehicles/{$vehicle->id}/controls/executions/{$execution->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('control_executions', ['id' => $execution->id]);
    }
}
