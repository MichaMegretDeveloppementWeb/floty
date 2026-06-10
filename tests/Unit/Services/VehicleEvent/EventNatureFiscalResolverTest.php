<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VehicleEvent;

use App\Models\VehicleEventNature;
use App\Services\VehicleEvent\EventNatureFiscalResolver;
use App\Support\VehicleEvent\EventNatureCatalog;
use Database\Seeders\VehicleEventNatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dérivation de la réductivité fiscale depuis les natures : ≥ 1 nature du
 * bloc réducteur figé ⇒ réducteur ; tout le reste (catalogue non réducteur,
 * saisie libre, vide) ⇒ non réducteur. Matching trim + insensible à la casse.
 */
final class EventNatureFiscalResolverTest extends TestCase
{
    use RefreshDatabase;

    private EventNatureFiscalResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleEventNatureSeeder::class);
        $this->resolver = $this->app->make(EventNatureFiscalResolver::class);
    }

    #[Test]
    public function chaque_nature_du_bloc_reducteur_rend_l_evenement_reducteur(): void
    {
        foreach (EventNatureCatalog::REDUCTIVE as $label) {
            $this->assertTrue(
                $this->resolver->hasReductiveNature([$label]),
                sprintf('« %s » doit être réductrice.', $label),
            );
        }
    }

    #[Test]
    public function une_seule_nature_reductrice_suffit_parmi_plusieurs(): void
    {
        $this->assertTrue($this->resolver->hasReductiveNature([
            'Entretien',
            EventNatureCatalog::REDUCTIVE[1],
            'Saisie libre quelconque',
        ]));
    }

    #[Test]
    public function le_matching_est_insensible_a_la_casse_et_aux_espaces(): void
    {
        $this->assertTrue($this->resolver->hasReductiveNature([
            '  fourrière (DEMANDE publique)  ',
        ]));
    }

    #[Test]
    public function les_natures_non_reductrices_et_libres_ne_sont_pas_reductrices(): void
    {
        $this->assertFalse($this->resolver->hasReductiveNature(EventNatureCatalog::NON_REDUCTIVE));
        $this->assertFalse($this->resolver->hasReductiveNature(['Carrosserie', 'Pneus hiver']));
        $this->assertFalse($this->resolver->hasReductiveNature([]));
    }

    #[Test]
    public function une_nature_utilisateur_homonyme_ne_devient_jamais_reductrice(): void
    {
        // « Ajouter à la liste » ne crée que du non-réducteur ; même si un
        // label utilisateur ressemble au bloc figé, seul le flag DB décide.
        VehicleEventNature::factory()->create(['label' => 'Sinistre avec immobilisation']);

        $this->assertFalse($this->resolver->hasReductiveNature(['Sinistre avec immobilisation']));
    }
}
