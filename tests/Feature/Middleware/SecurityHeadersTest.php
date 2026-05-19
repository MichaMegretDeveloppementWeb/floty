<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SecurityHeaders;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Anti-régression sur les en-têtes posés par {@see SecurityHeaders}
 * (CSP V1 stricte + headers défense en profondeur · HSTS, X-Frame-Options, etc.).
 */
final class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function csp_header_present_avec_directives_v1_obligatoires(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'Le header Content-Security-Policy doit être posé.');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);
        $this->assertStringContainsString("font-src 'self' data:", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    #[Test]
    public function headers_defense_en_profondeur_presents(): void
    {
        $response = $this->get('/login');

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame(
            'strict-origin-when-cross-origin',
            $response->headers->get('Referrer-Policy'),
        );
        $this->assertStringContainsString(
            'camera=()',
            (string) $response->headers->get('Permissions-Policy'),
        );
    }

    #[Test]
    public function permissions_policy_desactive_toutes_les_apis_browser_non_utilisees(): void
    {
        // Défense en profondeur · toutes les APIs browser non utilisées par
        // Floty doivent être explicitement désactivées pour qu'un XSS résiduel
        // ne puisse pas les exploiter.
        $response = $this->get('/login');
        $policy = (string) $response->headers->get('Permissions-Policy');

        $this->assertNotEmpty($policy, 'Le header Permissions-Policy doit être posé.');

        $disabledApis = [
            'accelerometer', 'ambient-light-sensor', 'autoplay', 'bluetooth',
            'camera', 'geolocation', 'gyroscope', 'magnetometer', 'microphone',
            'midi', 'payment', 'serial', 'usb',
        ];

        foreach ($disabledApis as $api) {
            $this->assertStringContainsString(
                "{$api}=()",
                $policy,
                "Permissions-Policy doit désactiver `{$api}` (défense en profondeur).",
            );
        }

        // `fullscreen=(self)` reste actif pour future modale PDF.
        $this->assertStringContainsString('fullscreen=(self)', $policy);
    }

    #[Test]
    public function csp_skip_en_environnement_local_pour_ne_pas_bloquer_la_debugbar(): void
    {
        // `script-src 'self'` bloque les <script> inline injectés par
        // Laravel Debugbar et le HMR Vite. En env `local`, on retire la
        // CSP pour préserver l'expérience dev. Les autres headers de
        // sécurité (X-Frame-Options, nosniff, etc.) restent appliqués.
        $this->app['env'] = 'local';

        $response = $this->get('/login');

        $this->assertNull(
            $response->headers->get('Content-Security-Policy'),
            'En env local, le header CSP ne doit pas être posé.',
        );
        // Les autres headers de sécurité doivent rester actifs.
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
