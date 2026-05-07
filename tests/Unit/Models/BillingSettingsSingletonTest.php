<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du singleton applicatif `BillingSettings::singleton()` (chantier T4
 * / Phase 14.P).
 *
 * L'implémentation passe de `first() + new save()` à `firstOrCreate(['id' => 1])`
 * pour réduire la fenêtre de race condition et garantir l'unicité de la
 * row même en cas d'appels parallèles.
 */
final class BillingSettingsSingletonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cree_la_ligne_si_table_vide(): void
    {
        self::assertSame(0, BillingSettings::query()->count());

        $settings = BillingSettings::singleton();

        self::assertSame(1, BillingSettings::query()->count());
        self::assertGreaterThan(0, $settings->id);
    }

    #[Test]
    public function retourne_la_meme_instance_apres_creation(): void
    {
        $first = BillingSettings::singleton();
        $second = BillingSettings::singleton();

        self::assertSame($first->id, $second->id);
        self::assertSame(1, BillingSettings::query()->count());
    }

    #[Test]
    public function ne_cree_pas_de_doublon_apres_appels_repetes(): void
    {
        // Plusieurs appels successifs ne doivent jamais créer de doublon.
        BillingSettings::singleton();
        BillingSettings::singleton();
        BillingSettings::singleton();

        self::assertSame(1, BillingSettings::query()->count());
    }

    #[Test]
    public function retourne_la_ligne_existante_apres_premiere_creation(): void
    {
        // Premier appel : création.
        $created = BillingSettings::singleton();
        $created->name = 'Sogema Rent';
        $created->save();

        // Deuxième appel : doit lire la ligne existante (pas de row vierge).
        $reloaded = BillingSettings::singleton();

        self::assertSame('Sogema Rent', $reloaded->name);
        self::assertSame(1, BillingSettings::query()->count());
    }
}
