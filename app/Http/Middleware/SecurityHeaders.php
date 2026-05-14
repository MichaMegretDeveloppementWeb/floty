<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de sécurité ajoutés à toutes les réponses du groupe `web`.
 *
 * - `X-Frame-Options: DENY` · empêche l'inclusion en iframe (anti
 *   clickjacking). L'application Floty ne s'embed pas légitimement
 *   dans un autre site.
 * - `X-Content-Type-Options: nosniff` · empêche le MIME-sniffing
 *   navigateur, oblige le respect du Content-Type déclaré.
 * - `Referrer-Policy: strict-origin-when-cross-origin` · limite la
 *   fuite d'URL de pages internes vers les sites tiers.
 * - `Permissions-Policy: ...` · désactive les API browser non
 *   utilisées (caméra, micro, géoloc).
 * - `Strict-Transport-Security` · force HTTPS pendant 1 an, posé
 *   uniquement sur connexion sécurisée pour ne pas verrouiller le
 *   dev local Herd HTTP.
 * - `Content-Security-Policy` · politique V1 stricte (ADR-0011 § 6).
 *   `'unsafe-inline'` style est admis V1 pour Tailwind runtime · à
 *   durcir V2 avec nonces si besoin. Cf. plan-remédiation Vague 1
 *   Lot 1 D3 (F-30-001). **Skip en environnement `local`** ·
 *   `script-src 'self'` bloque les scripts inline du Laravel Debugbar
 *   et du HMR Vite, qui n'ont aucune utilité en production. Les autres
 *   headers de sécurité restent appliqués partout.
 */
final class SecurityHeaders
{
    private const string CSP_V1 =
        "default-src 'self'; "
        ."script-src 'self'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data:; "
        ."font-src 'self' data:; "
        ."connect-src 'self'; "
        ."frame-ancestors 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self';";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! App::environment('local')) {
            $response->headers->set('Content-Security-Policy', self::CSP_V1);
        }

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        return $response;
    }
}
