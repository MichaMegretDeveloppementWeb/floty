<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\VehicleEventNature;
use App\Support\VehicleEvent\EventNatureCatalog;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fous du catalogue de natures : contenu du bloc réducteur figé,
 * idempotence du re-seed (updateOrCreate, le user re-seedera la prod
 * manuellement) et préservation des entrées utilisateur.
 */
final class VehicleEventNatureSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_seeder_cree_le_catalogue_de_base_complet(): void
    {
        $this->seed(VehicleEventNatureSeeder::class);

        $reductive = VehicleEventNature::query()
            ->where('is_fiscally_reductive', true)
            ->pluck('label')
            ->all();

        $nonReductive = VehicleEventNature::query()
            ->where('is_fiscally_reductive', false)
            ->pluck('label')
            ->all();

        $this->assertEqualsCanonicalizing(EventNatureCatalog::REDUCTIVE, $reductive);
        $this->assertEqualsCanonicalizing(EventNatureCatalog::NON_REDUCTIVE, $nonReductive);
    }

    #[Test]
    public function un_re_seed_ne_cree_aucun_doublon(): void
    {
        $this->seed(VehicleEventNatureSeeder::class);
        $this->seed(VehicleEventNatureSeeder::class);

        $expected = count(EventNatureCatalog::REDUCTIVE) + count(EventNatureCatalog::NON_REDUCTIVE);

        $this->assertSame($expected, VehicleEventNature::query()->count());
    }

    #[Test]
    public function un_re_seed_preserve_les_entrees_utilisateur(): void
    {
        $this->seed(VehicleEventNatureSeeder::class);

        VehicleEventNature::factory()->create(['label' => 'Carrosserie']);

        $this->seed(VehicleEventNatureSeeder::class);

        $custom = VehicleEventNature::query()->where('label', 'Carrosserie')->first();

        $this->assertNotNull($custom);
        $this->assertFalse($custom->is_fiscally_reductive);
    }

    #[Test]
    public function un_re_seed_retablit_le_flag_reducteur_du_bloc_fige(): void
    {
        $this->seed(VehicleEventNatureSeeder::class);

        // Une corruption manuelle du flag est réparée par le re-seed.
        VehicleEventNature::query()
            ->where('label', EventNatureCatalog::REDUCTIVE[0])
            ->update(['is_fiscally_reductive' => false]);

        $this->seed(VehicleEventNatureSeeder::class);

        $this->assertTrue(
            VehicleEventNature::query()
                ->where('label', EventNatureCatalog::REDUCTIVE[0])
                ->firstOrFail()
                ->is_fiscally_reductive,
        );
    }
}
