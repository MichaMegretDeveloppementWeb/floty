<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que `config('session.secure')` a un défaut sûr.
 *
 * Doctrine · ADR-0011 § 2 prescrit `secure = true` en prod. Sans variable
 * `SESSION_SECURE_COOKIE` posée, le défaut doit s'aligner sur l'environnement.
 *
 * Couvre · F-S2-001 (plan-remédiation Vague 1 Lot 1 D5).
 */
final class SessionSecureCookieTest extends TestCase
{
    #[Test]
    public function config_session_secure_default_uses_app_env_production_check(): void
    {
        // Anti-régression · le défaut posé dans config/session.php doit
        // rester un test `env('APP_ENV') === 'production'` pour garantir
        // un cookie Secure en prod même si SESSION_SECURE_COOKIE est
        // absente du .env de prod.
        //
        // On inspecte directement le code source de config/session.php
        // plutôt que d'exécuter `env()` runtime (qui pose des problèmes
        // d'isolation de test). On ne peut pas non plus utiliser
        // `app()->environment('production')` dans config/session.php car
        // le container n'est pas bootstrappé au moment de l'évaluation
        // du fichier (erreur "Class env does not exist").

        $configContent = file_get_contents(config_path('session.php'));

        $this->assertStringContainsString(
            "env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')",
            $configContent,
            "config/session.php doit utiliser env('APP_ENV') === 'production' comme fallback de SESSION_SECURE_COOKIE.",
        );
    }

    #[Test]
    public function env_example_documents_secure_cookie_true_as_prod_default(): void
    {
        // Anti-régression · .env.example doit suggérer SESSION_SECURE_COOKIE=true
        // car ce fichier est souvent copié comme base prod. Si un admin oublie
        // de l'adapter à dev, au moins le défaut est sûr.

        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^SESSION_SECURE_COOKIE=true$/m',
            $envExample,
            '.env.example doit poser SESSION_SECURE_COOKIE=true par défaut (override en dev local).',
        );
    }
}
