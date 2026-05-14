<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filet de sécurité · vérifie qu'AUCUNE route du group `user.*`
 * (préfixe `/app`, middleware `auth`) n'est accessible sans
 * authentification.
 *
 * Pour les routes paramétrées (`/companies/{company}`, etc.), on
 * substitue `1` comme placeholder · le middleware `auth` intercepte
 * AVANT le route binding model, donc la réponse attendue (302/401/419)
 * ne dépend pas de l'existence de l'entité. Toutes les routes user
 * Floty utilisent des IDs numériques (pas d'UUID, slug ou signed) ·
 * `1` matche systématiquement les contraintes implicites.
 *
 * Un commit futur qui retirerait par mégarde le middleware `auth` sur
 * une route paramétrée serait immédiatement attrapé · cf. plan-remédiation
 * Vague 1 Lot 2 D3 (F-10-007).
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
