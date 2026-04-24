<?php

namespace App\Http\Middleware;

use App\Data\Auth\CurrentUserData;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shared props Inertia Floty.
 *
 * Exposées à chaque page sans duplication côté controller. Typées strict
 * côté TypeScript via {@see \resources\js\types\inertia.d.ts} (déclaration
 * `PageProps`).
 *
 * Invariants :
 *   - `appName` : chaîne non vide, stable pour toute la durée de vie du
 *     déploiement.
 *   - `auth.user` : `null` tant qu'aucun utilisateur n'est authentifié,
 *     sinon un {@see CurrentUserData} (prévu en phase 03).
 *     En phase 01 on expose juste l'identifiant et le nom — suffisant pour
 *     tester la chaîne bout-en-bout sans avoir à attendre la DTO auth.
 *   - `flash.*` : quatre canaux indépendants correspondants aux quatre
 *     tons de Toast du design system (success / error / warning / info).
 *     Le controller alimente via `->with('toast-success', 'Message')` et
 *     le front lit `flash.success`.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            'auth' => [
                'user' => fn () => $this->resolveAuthenticatedUser($request),
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('toast-success'),
                'error' => fn () => $request->session()->get('toast-error'),
                'warning' => fn () => $request->session()->get('toast-warning'),
                'info' => fn () => $request->session()->get('toast-info'),
            ],
        ];
    }

    /**
     * Représentation minimale de l'utilisateur connecté, exposée aux pages
     * Inertia. Remplacée en phase 03 par un DTO {@see CurrentUserData}
     * dès que ce dernier existe.
     *
     * @return array{id: int, name: string, email: string}|null
     */
    private function resolveAuthenticatedUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
