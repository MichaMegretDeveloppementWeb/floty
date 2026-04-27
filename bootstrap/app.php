<?php

declare(strict_types=1);

use App\Exceptions\BaseAppException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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

        // Réponses globales selon le statut HTTP final, après le render
        // par défaut de Laravel (pour 419 / 403 / 404 / 500 / 503).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // Visites Inertia : intercepter 419 (CSRF) et 403 pour
            // rester sur la page avec un toast plutôt qu'une page d'erreur.
            if ($request->header('X-Inertia')) {
                return match ($status) {
                    419 => back()->with('toast-warning', 'Votre session a expiré. Veuillez réessayer.'),
                    403 => back()->with('toast-error', 'Action non autorisée.'),
                    default => $response,
                };
            }

            // Pages d'erreur Inertia (404 / 500 / 503) en non-local et
            // non-testing uniquement — on garde Whoops Laravel en local
            // pour le debug, et le testing utilise le framework natif.
            if (
                ! app()->environment(['local', 'testing'])
                && in_array($status, [404, 500, 503], true)
                && ! $request->expectsJson()
            ) {
                return Inertia::render('Errors/Index', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
