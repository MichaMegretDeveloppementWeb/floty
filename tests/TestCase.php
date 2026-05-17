<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TestCase de base Floty · étend `Illuminate\Foundation\Testing\TestCase`
 * pour bénéficier du bootstrap Laravel (container, facades, helpers).
 *
 * **Convention héritage** ·
 * - Tous les tests Feature + tests Unit nécessitant un cycle DB / le
 *   container Laravel héritent de cette classe.
 * - Les tests Unit purs (algorithmes, helpers stateless · ex.
 *   `OptimalRateBreakdownTest`) héritent directement de
 *   `PHPUnit\Framework\TestCase` pour éviter le coût de bootstrap inutile.
 *
 * Helpers communs à mutualiser ici si pattern récurrent émerge.
 * Ne pas y mettre de logique métier · réservé à l'infrastructure de test.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou ironclad · refuse de lancer un test si la connexion DB
     * pointe sur autre chose que `floty_testing`. Empêche définitivement
     * toute fuite RefreshDatabase vers la base dev (`floty`).
     *
     * Précédent incident · 2026-05-17, `bootstrap/cache/config.php` figé
     * en env=local avec DB=floty empêchait phpunit.xml d'override
     * DB_DATABASE → RefreshDatabase a flushé la base dev 3 fois. Garde-fou
     * en plus du `force="true"` dans phpunit.xml pour défense en profondeur.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $dbName = DB::connection()->getDatabaseName();
        if ($dbName !== 'floty_testing') {
            throw new RuntimeException(sprintf(
                "Garde-fou test · refus de lancer un test sur la base '%s' (attendu · 'floty_testing'). ".
                'Cause probable · `bootstrap/cache/config.php` figé en env=local. '.
                'Fix · `php artisan config:clear` puis relancer les tests.',
                $dbName,
            ));
        }
    }
}
