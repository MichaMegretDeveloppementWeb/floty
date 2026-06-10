<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\VehicleEventDetailSuggestion;
use Database\Seeders\VehicleEventDetailSuggestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fous de la liste de départ des détails : idempotence du re-seed
 * (updateOrCreate, le user re-seedera la prod) et préservation des entrées
 * ajoutées par l'utilisateur.
 */
final class VehicleEventDetailSuggestionSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_seeder_cree_la_liste_de_depart(): void
    {
        $this->seed(VehicleEventDetailSuggestionSeeder::class);

        $labels = VehicleEventDetailSuggestion::query()->pluck('label')->all();

        $this->assertContains('Vidange', $labels);
        $this->assertContains('Passage au contrôle technique', $labels);
        $this->assertGreaterThanOrEqual(15, count($labels));
    }

    #[Test]
    public function un_re_seed_ne_cree_aucun_doublon(): void
    {
        $this->seed(VehicleEventDetailSuggestionSeeder::class);
        $before = VehicleEventDetailSuggestion::query()->count();

        $this->seed(VehicleEventDetailSuggestionSeeder::class);

        $this->assertSame($before, VehicleEventDetailSuggestion::query()->count());
    }

    #[Test]
    public function un_re_seed_preserve_les_entrees_utilisateur(): void
    {
        $this->seed(VehicleEventDetailSuggestionSeeder::class);

        VehicleEventDetailSuggestion::factory()->create(['label' => 'Pose attelage']);

        $this->seed(VehicleEventDetailSuggestionSeeder::class);

        $this->assertDatabaseHas('vehicle_event_detail_suggestions', ['label' => 'Pose attelage']);
    }
}
