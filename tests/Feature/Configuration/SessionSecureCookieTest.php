<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que `config('session.secure')` a un défaut sûr aligné sur ADR-0011 § 2
 * (secure = true en prod).
 */
final class SessionSecureCookieTest extends TestCase
{
    #[Test]
    public function config_session_secure_default_uses_app_env_production_check(): void
    {
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
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^SESSION_SECURE_COOKIE=true$/m',
            $envExample,
            '.env.example doit poser SESSION_SECURE_COOKIE=true par défaut (override en dev local).',
        );
    }
}
