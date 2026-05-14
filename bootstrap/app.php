<?php

declare(strict_types=1);

use App\Exceptions\BaseAppException;
use App\Exceptions\UserFacingExceptionRenderer;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Segmentation Floty : auth/ et user/ chargés en plus de web/.
            // Tous partagent le middleware group "web" (sessions, CSRF, cookies)
            // imposé par Inertia. L'authentification propre est appliquée par
            // groupe DANS chaque fichier (`auth.php` et `user.php`).
            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware('web')->group(base_path('routes/user.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        // ADR-0011 § 1 · faire confiance aux proxies en amont pour
        // résoudre correctement `isSecure()` derrière LB Hostinger.
        // `at: '*'` est acceptable car Floty est derrière un LB
        // Hostinger qu'on ne peut pas lister par IP statique. Sans
        // cette config, le cookie session `Secure` (cf. ADR-0011 § 2)
        // ne se déclenche pas et le rate-limit IP (cf. § 3 rev. 1.1)
        // utiliserait l'IP du LB au lieu de l'IP réelle.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render des exceptions métier Floty.
        // - Requêtes Ajax/JSON (ex. useApi) → JSON structuré 422 avec
        //   `message` (français, getUserMessage) et `code` (class basename).
        // - Visites web/Inertia → flash 'toast-error' + back() pour rester
        //   sur la page courante avec les inputs préservés.
        $exceptions->render(function (BaseAppException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getUserMessage(),
                    'code' => class_basename($e),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return back()->withInput()->with('toast-error', $e->getUserMessage());
        });

        // Render JSON ciblé pour ValidationException (endpoints `useApi.post`
        // / `postJson`). Compose un `message` français explicite listant les
        // champs invalides — sans dépendre de `lang:publish` ni des clés
        // brutes Laravel comme `validation.required`. `errors` reste au
        // format standard (map `field => list<message>`) pour permettre,
        // plus tard, l'affichage inline par champ côté front.
        //
        // Guard `expectsJson()` : on ne touche pas le redirect-back-with-
        // errors d'Inertia côté formulaires HTML classiques.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null; // laisse Laravel gérer (redirect back + flash errors)
            }

            $errors = $e->errors();
            $fields = array_keys($errors);
            $count = count($fields);

            if ($count === 0) {
                $message = 'La requête est invalide.';
            } elseif ($count === 1) {
                $field = $fields[0];
                $first = $errors[$field][0] ?? 'invalide';
                // Si le message est encore une clé brute `validation.xxx`
                // (pas de lang/fr publié), on tombe sur un format générique
                // FR plutôt que d'exposer la clé.
                $detail = str_starts_with($first, 'validation.')
                    ? 'invalide ou manquant'
                    : $first;
                $message = sprintf('Le champ « %s » est %s.', $field, $detail);
            } elseif ($count <= 3) {
                $message = sprintf(
                    'Plusieurs champs sont invalides : %s.',
                    implode(', ', $fields),
                );
            } else {
                $head = array_slice($fields, 0, 3);
                $remaining = $count - 3;
                $message = sprintf(
                    'Plusieurs champs sont invalides : %s et %d autre%s.',
                    implode(', ', $head),
                    $remaining,
                    $remaining > 1 ? 's' : '',
                );
            }

            return response()->json([
                'message' => $message,
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // Réponses globales selon le statut HTTP final, après le render
        // par défaut de Laravel (pour 419 / 403 / 404 / 500 / 503).
        //
        // Doctrine UX Floty (chantier T2 / Phase 14.N) : pour les visites
        // HTML/Inertia normales, on préfère un redirect transparent vers
        // une page utile (index du domaine concerné ou dashboard) + un
        // toast explicite, plutôt qu'une page d'erreur isolée. Les
        // requêtes XHR/JSON conservent la sémantique HTTP standard.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // XHR/JSON : on laisse Laravel rendre le statut natif (404/403/etc.).
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            // Visites Inertia : intercepter 419 (CSRF), 403 (autorisation)
            // et 429 (rate-limit middleware) pour rester sur la page courante
            // avec un toast (l'action a échoué mais on ne veut pas naviguer
            // ailleurs depuis une simple soumission refusée). Sans cette
            // interception, Inertia reçoit la page HTML d'erreur brute et
            // l'affiche dans une modale d'erreur générique · UX médiocre
            // sur des routes guest/non indexées (login, forgot-password,
            // reset-password, change-password). Le 404 Inertia retombe
            // dans la logique générique ci-dessous (redirect domaine).
            if ($request->header('X-Inertia') && in_array($status, [419, 403, 429], true)) {
                return match ($status) {
                    419 => back()->with('toast-warning', 'Votre session a expiré. Veuillez réessayer.'),
                    403 => back()->with('toast-error', 'Action non autorisée.'),
                    429 => back()->withInput()->with(
                        'toast-warning',
                        'Trop de tentatives. Patientez quelques minutes avant de réessayer.',
                    ),
                };
            }

            // 404 ModelNotFoundException (route binding) : redirect vers
            // l'index du domaine concerné. Laravel encapsule souvent la
            // ModelNotFoundException dans une NotFoundHttpException — on
            // remonte la chaîne `previous` pour récupérer le Model.
            $modelNotFound = $e instanceof ModelNotFoundException
                ? $e
                : (
                    $e instanceof NotFoundHttpException
                    && $e->getPrevious() instanceof ModelNotFoundException
                        ? $e->getPrevious()
                        : null
                );

            if ($modelNotFound !== null) {
                return UserFacingExceptionRenderer::renderModelNotFound($modelNotFound);
            }

            // 403 (toute origine) : redirect dashboard + toast.
            if ($status === 403) {
                return UserFacingExceptionRenderer::renderAuthorization(
                    $e instanceof AuthorizationException ? $e : new AuthorizationException,
                );
            }

            // 404 générique (URL inexistante, findOrFail manuel sans Model
            // bind) : redirect dashboard + toast « page introuvable ».
            if ($status === 404) {
                return UserFacingExceptionRenderer::renderNotFoundHttp(
                    $e instanceof NotFoundHttpException ? $e : new NotFoundHttpException,
                );
            }

            // Pages d'erreur Inertia (500 / 503) en non-local et
            // non-testing uniquement — on garde Whoops Laravel en local
            // pour le debug, et le testing utilise le framework natif.
            if (
                ! app()->environment(['local', 'testing'])
                && in_array($status, [500, 503], true)
            ) {
                return Inertia::render('Errors/Index', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
