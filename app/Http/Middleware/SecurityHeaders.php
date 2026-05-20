<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the standard security headers to every response of the `web`
 * middleware group:
 *
 *   - `X-Frame-Options: DENY` · prevents iframe embedding (clickjacking).
 *   - `X-Content-Type-Options: nosniff` · forces the declared Content-Type.
 *   - `Referrer-Policy: strict-origin-when-cross-origin` · limits URL
 *     leakage to third parties.
 *   - `Permissions-Policy` · disables every browser API Floty does not
 *     use (defence in depth against XSS); `fullscreen=(self)` is kept
 *     for the planned fullscreen PDF modal.
 *   - `Strict-Transport-Security` · forces HTTPS for one year; only
 *     emitted on secure requests to avoid trapping the local Herd HTTP
 *     setup.
 *   - `Content-Security-Policy` · strict V1 policy (ADR-0011 § 6).
 *     `'unsafe-inline'` is allowed on styles for Tailwind runtime; to
 *     be tightened later with nonces. The CSP is skipped in the `local`
 *     environment because `script-src 'self'` would block both the
 *     Laravel Debugbar and the Vite HMR inline scripts, which have no
 *     production purpose; other headers still apply everywhere.
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

    /**
     * Apply every security header to the outgoing response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

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

    /**
     * Build the `Permissions-Policy` header value, disabling every
     * browser API except fullscreen (kept for a planned PDF modal).
     */
    private function permissionsPolicy(): string
    {
        return implode(', ', [
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'bluetooth=()',
            'camera=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'serial=()',
            'usb=()',
        ]);
    }
}
