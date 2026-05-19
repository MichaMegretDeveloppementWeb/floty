<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filet de sécurité · vérifie qu'AUCUNE route du group `user.*` (préfixe `/app`,
 * middleware `auth`) n'est accessible sans authentification. Pour les routes
 * paramétrées, on substitue `1` comme placeholder car le middleware `auth`
 * intercepte AVANT le route binding model.
 */
final class UserRoutesAuthTest extends TestCase
{
    #[Test]
    public function toutes_les_routes_user_redirigent_vers_login_si_non_authentifie(): void
    {
        /** @var list<\Illuminate\Routing\Route> $allRoutes */
        $allRoutes = Route::getRoutes()->getRoutes();
        $userRoutes = collect($allRoutes)
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'user.'));

        $this->assertGreaterThan(
            0,
            $userRoutes->count(),
            'Aucune route user.* trouvée · le test est vide.',
        );

        foreach ($userRoutes as $route) {
            // Substituer chaque paramètre `{xxx}` par `1` · la regex
            // remplace aussi les URIs avec plusieurs paramètres
            // (`/drivers/{driver}/memberships/{companyId}` →
            // `/drivers/1/memberships/1`).
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri()) ?? $route->uri();

            $methods = array_diff($route->methods(), ['HEAD']);
            foreach ($methods as $method) {
                $response = $this->call($method, '/'.ltrim($uri, '/'));

                // Statut acceptable · guest est redirigé login (302),
                // ou rejet JSON 401 (XHR) ou CSRF expiré 419. Tout
                // autre statut indique une route non protégée.
                $this->assertContains(
                    $response->status(),
                    [302, 401, 419],
                    sprintf(
                        '[%s %s] (%s) devrait rediriger ou rejeter (302/401/419), reçu %d',
                        $method,
                        $uri,
                        $route->getName(),
                        $response->status(),
                    ),
                );
            }
        }
    }
}
