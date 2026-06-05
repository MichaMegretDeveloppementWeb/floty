<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Models\ControlDefinition;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de schéma du journal de rappels (Chantier B / B3) : CHECK kind/target
 * + UNIQUE occurrence.
 */
final class ReminderLogSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(int $vehicleId, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $vehicleId,
            'control_definition_id' => null,
            'vehicle_control_override_id' => null,
            'target_key' => 'def:1',
            'due_on' => '2026-06-20',
            'reminder_on' => '2026-06-05',
            'kind' => 'before',
            'recipients_count' => 1,
            'sent_at' => '2026-06-05 07:00:00',
        ], $overrides);
    }

    #[Test]
    public function le_check_rejette_un_kind_hors_domaine(): void
    {
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('control_reminder_logs')->insert($this->row($vehicle->id, [
            'control_definition_id' => $definition->id,
            'kind' => 'bogus',
        ]));
    }

    #[Test]
    public function le_check_rejette_une_cible_double(): void
    {
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();
        $override = VehicleControlOverride::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->expectException(QueryException::class);

        DB::table('control_reminder_logs')->insert($this->row($vehicle->id, [
            'control_definition_id' => $definition->id,
            'vehicle_control_override_id' => $override->id,
        ]));
    }

    #[Test]
    public function le_check_rejette_une_cible_absente(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('control_reminder_logs')->insert($this->row($vehicle->id));
    }

    #[Test]
    public function l_unique_rejette_une_occurrence_dupliquee(): void
    {
        $vehicle = Vehicle::factory()->create();
        $definition = ControlDefinition::factory()->create();
        $row = $this->row($vehicle->id, [
            'control_definition_id' => $definition->id,
            'target_key' => 'def:'.$definition->id,
        ]);

        DB::table('control_reminder_logs')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('control_reminder_logs')->insert($row);
    }
}
