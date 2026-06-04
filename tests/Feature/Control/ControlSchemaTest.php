<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Enums\Control\ControlAnchor;
use App\Models\ControlDefinition;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de schéma des tables de configuration des contrôles (Chantier B / B1) :
 * contraintes CHECK MySQL + cohérence du mapping ancre -> colonne véhicule.
 */
final class ControlSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chaque_ancre_pointe_vers_une_colonne_existante_de_vehicles(): void
    {
        foreach (ControlAnchor::cases() as $anchor) {
            self::assertTrue(
                Schema::hasColumn('vehicles', $anchor->vehicleColumn()),
                "La colonne {$anchor->vehicleColumn()} doit exister sur vehicles.",
            );
        }
    }

    #[Test]
    public function le_check_rejette_une_ancre_hors_domaine(): void
    {
        $this->expectException(QueryException::class);

        DB::table('control_definitions')->insert([
            'name' => 'Bidon',
            'anchor' => 'bogus_anchor',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);
    }

    #[Test]
    public function le_check_rejette_une_duree_initiale_non_positive(): void
    {
        $this->expectException(QueryException::class);

        DB::table('control_definitions')->insert([
            'name' => 'Bidon',
            'anchor' => 'acquisition',
            'initial_duration_value' => 0,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ]);
    }

    #[Test]
    public function le_check_rejette_un_delta_settings_rattache_a_une_definition(): void
    {
        $definition = ControlDefinition::factory()->create();

        $this->expectException(QueryException::class);

        // Niveau settings : control_definition_id doit rester nul (cohérence scope).
        DB::table('control_recipient_deltas')->insert([
            'level' => 'settings',
            'control_definition_id' => $definition->id,
            'operation' => 'include',
            'email' => 'x@exemple.fr',
            'name' => 'X',
        ]);
    }

    #[Test]
    public function le_check_rejette_un_include_sans_nom(): void
    {
        $definition = ControlDefinition::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('control_recipient_deltas')->insert([
            'level' => 'definition',
            'control_definition_id' => $definition->id,
            'operation' => 'include',
            'email' => 'x@exemple.fr',
            'name' => null,
        ]);
    }
}
