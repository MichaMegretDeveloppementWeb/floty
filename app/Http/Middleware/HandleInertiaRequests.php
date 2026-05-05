<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Auth\CurrentUserData;
use App\Data\Shared\FiscalSharedData;
use App\Data\Shared\FlashData;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shared props Inertia Floty.
 *
 * Exposées à chaque page sans duplication côté controller. Typées strict
 * côté TypeScript via les DTOs Spatie Data (générés automatiquement dans
 * `resources/js/types/generated/generated.d.ts`).
 *
 * Invariants :
 *   - `appName` : chaîne non vide, stable pour toute la durée de vie du
 *     déploiement.
 *   - `auth.user` : `null` tant qu'aucun utilisateur n'est authentifié,
 *     sinon un {@see CurrentUserData}.
 *   - `flash` : quatre canaux indépendants correspondants aux quatre tons
 *     de Toast du design system (success / error / warning / info).
 *     Le controller alimente via `->with('toast-success', 'Message')` et
 *     le front lit `flash.success`.
 *   - `fiscal.availableYears` : années configurées dans le moteur fiscal
 *     (cf. `config/floty.fiscal.available_years`). Source unique de vérité
 *     pour peupler les sélecteurs d'année locaux. Chantier J (ADR-0020) a
 *     retiré `currentYear` (plus de session active).
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
                'user' => fn (): ?CurrentUserData => $this->resolveAuthenticatedUser($request),
            ],

            'flash' => fn (): FlashData => new FlashData(
                success: $request->session()->get('toast-success'),
                error: $request->session()->get('toast-error'),
                warning: $request->session()->get('toast-warning'),
                info: $request->session()->get('toast-info'),
            ),

            'fiscal' => fn (): FiscalSharedData => new FiscalSharedData(
                availableYears: array_map(
                    'intval',
                    config('floty.fiscal.available_years', []),
                ),
            ),
        ];
    }

    private function resolveAuthenticatedUser(Request $request): ?CurrentUserData
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return CurrentUserData::fromUser($user);
    }
}
