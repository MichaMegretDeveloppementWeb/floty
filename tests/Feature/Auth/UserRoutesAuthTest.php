<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test : aucune route `user.*` n'est accessible sans
 * authentification. Garde-fou contre les régressions futures
 * (ajout de route oubliant `auth` middleware).
 *
 * En l'absence de Policies métier (D11 - pas de modèle d'ownership
 * dans le data model MVP), ce test est notre filet de sécurité
 * unique pour vérifier que l'accès aux ressources est protégé.
 */
final class UserRoutesAuthTest extends TestCase
{
    #[Test]
    public function toutes_les_routes_user_redirigent_vers_login_si_non_authentifie(): void
    {
        $userRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'user.'));

        $this->assertGreaterThan(
            0,
            $userRoutes->count(),
            'Aucune route user.* trouvée - le test est vide.',
        );

        foreach ($userRoutes as $route) {
            $methods = array_diff($route->methods(), ['HEAD']);
            foreach ($methods as $method) {
                // On évite les routes paramétrées (none ici, mais robustesse).
                if (str_contains($route->uri(), '{')) {
                    continue;
                }

                $response = $this->call($method, '/'.ltrim($route->uri(), '/'));

                $this->assertContains(
                    $response->status(),
                    [302, 401, 419],
                    sprintf(
                        '[%s %s] (%s) devrait rediriger ou rejeter (302/401/419), reçu %d',
                        $method,
                        $route->uri(),
                        $route->getName(),
                        $response->status(),
                    ),
                );
            }
        }
    }
}
