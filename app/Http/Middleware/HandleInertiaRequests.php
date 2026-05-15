<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Auth\CurrentUserData;
use App\Data\Shared\FlashData;
use App\Data\Shared\ToastEntryData;
use App\Support\Toasts\ToastDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Spatie\LaravelData\DataCollection;

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
 *
 * **Chantier η Phase 5** : la shared prop `fiscal.availableYears` a été
 * supprimée. Chaque page consommatrice reçoit désormais sa prop locale
 * `yearScope` ({@see App\Data\Shared\YearScopeData}) · alimentée soit par
 * `AvailableYearsResolver` (scope contrats), soit par
 * `FiscalRuleRegistry::registeredYears()` (scope moteur fiscal).
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

            'flash' => fn (): FlashData => $this->buildFlashData($request),
        ];
    }

    /**
     * Lot 5 D6 (F-19-007 + bug back-button) · combine les 4 anciens
     * canaux flash scalaires (rétrocompat ~30 controllers existants)
     * et la pile accumulée poussée par {@see ToastDispatcher} dans une
     * unique liste typée `flash.toasts: ToastEntryData[]`. Chaque
     * entry porte un ID unique consommé par le front pour la dédup
     * back-button.
     */
    private function buildFlashData(Request $request): FlashData
    {
        $session = $request->session();

        $success = $session->get('toast-success');
        $error = $session->get('toast-error');
        $warning = $session->get('toast-warning');
        $info = $session->get('toast-info');

        $toasts = [];

        // Conversion des 4 anciens canaux en ToastEntry · ID virtuel
        // basé sur tone + UUID pour garantir l'unicité (pas de collision
        // possible si le même message texte est posté en double).
        foreach (['success' => $success, 'error' => $error, 'warning' => $warning, 'info' => $info] as $tone => $message) {
            if ($message !== null && $message !== '') {
                $toasts[] = new ToastEntryData(
                    id: (string) Str::uuid(),
                    tone: $tone,
                    message: $message,
                );
            }
        }

        // Pile accumulée via ToastDispatcher · chaque entry porte
        // déjà son UUID (généré au push).
        /** @var list<array{id: string, tone: string, message: string}> $accumulated */
        $accumulated = $session->get(ToastDispatcher::SESSION_KEY, []);
        foreach ($accumulated as $entry) {
            $toasts[] = new ToastEntryData(
                id: $entry['id'],
                tone: $entry['tone'],
                message: $entry['message'],
            );
        }

        return new FlashData(
            success: $success,
            error: $error,
            warning: $warning,
            info: $info,
            toasts: new DataCollection(ToastEntryData::class, $toasts),
        );
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
