<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de sécurité ajoutés à toutes les réponses du groupe `web`.
 *
 * - `X-Frame-Options: DENY` — empêche l'inclusion en iframe (anti
 *   clickjacking). L'application Floty ne s'embed pas légitimement
 *   dans un autre site.
 * - `X-Content-Type-Options: nosniff` — empêche le MIME-sniffing
 *   navigateur, oblige le respect du Content-Type déclaré.
 * - `Referrer-Policy: strict-origin-when-cross-origin` — limite la
 *   fuite d'URL de pages internes vers les sites tiers.
 * - `Permissions-Policy: ...` — désactive les API browser non
 *   utilisées (caméra, micro, géoloc).
 * - `Strict-Transport-Security` — force HTTPS pendant 1 an, posé
 *   uniquement sur connexion sécurisée pour ne pas verrouiller le
 *   dev local Herd HTTP.
 *
 * **CSP volontairement non posé ici** : nécessite un mode
 * `report-only` puis durcissement progressif. À traiter dans une
 * phase sécurité dédiée (cf. decisions.md D14).
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        return $response;
    }
}
