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
            // auth/ and user/ are loaded on top of web/; they all share the
            // `web` middleware group (sessions, CSRF, cookies) required by
            // Inertia. Authentication is enforced per group inside each file.
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

        // Trust upstream proxies so `isSecure()`, the `Secure` session
        // cookie and the IP-based rate limiter resolve correctly behind the
        // Hostinger load balancer (ADR-0011 § 1-3). `at: '*'` is acceptable
        // because the LB cannot be listed by static IP.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions: JSON 422 with `message` + `code` for XHR
        // clients, flash `toast-error` + back() for Inertia visits.
        $exceptions->render(function (BaseAppException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getUserMessage(),
                    'code' => class_basename($e),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return back()->withInput()->with('toast-error', $e->getUserMessage());
        });

        // JSON render for ValidationException on XHR endpoints (useApi.post
        // / postJson). Builds a French `message` that lists the invalid
        // fields explicitly without depending on `lang:publish` or exposing
        // raw `validation.xxx` keys. `errors` keeps the standard
        // field => list<message> shape for inline display.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $errors = $e->errors();
            $fields = array_keys($errors);
            $count = count($fields);

            if ($count === 0) {
                $message = 'La requête est invalide.';
            } elseif ($count === 1) {
                $field = $fields[0];
                $first = $errors[$field][0] ?? 'invalide';
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

        // Global response shaping by HTTP status. For HTML/Inertia visits,
        // soft-fail to a useful page (back() or domain index) with a toast
        // rather than showing an isolated error page. XHR/JSON requests
        // keep standard HTTP semantics.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            // Inertia visits: intercept CSRF (419), authorization (403) and
            // middleware rate-limit (429) to stay on the current page with
            // a toast rather than navigating to an error page.
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

            // 404 from route model binding: redirect to the domain index.
            // Laravel often wraps ModelNotFoundException in a
            // NotFoundHttpException — unwrap to recover the Model.
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

            if ($status === 403) {
                return UserFacingExceptionRenderer::renderAuthorization(
                    $e instanceof AuthorizationException ? $e : new AuthorizationException,
                );
            }

            if ($status === 404) {
                return UserFacingExceptionRenderer::renderNotFoundHttp(
                    $e instanceof NotFoundHttpException ? $e : new NotFoundHttpException,
                );
            }

            // Inertia error pages for 500/503 outside of local and testing
            // environments (local keeps Whoops, testing uses the framework
            // native behaviour).
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
