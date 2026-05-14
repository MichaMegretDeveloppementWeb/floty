<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que `config('data.var_dumper_caster_mode')` a un défaut sûr.
 *
 * Doctrine · plan-remédiation Vague 1 Lot 6 D14 (F-33-012) · sans
 * `SPATIE_DATA_CASTER_MODE` posée, le défaut doit rester `'production'`
 * (silencieux), pas `'development'` (verbeux, expose les détails internes
 * des DTOs dans les dumps).
 *
 * On inspecte directement le code source de `config/data.php` plutôt
 * que d'exécuter `env()` runtime (l'env est figé au boot de PHPUnit,
 * donc difficilement testable runtime sans bootstrap explicite).
 */
final class DataCasterModeTest extends TestCase
{
    #[Test]
    public function config_data_caster_mode_falls_back_to_production_when_env_absent(): void
    {
        // Anti-régression · le défaut posé dans config/data.php doit
        // rester `env('SPATIE_DATA_CASTER_MODE', 'production')`. Si on
        // remet `'development'` en dur, on régresse sur la doctrine
        // (Lot 6 D14 F-33-012).

        $configContent = file_get_contents(config_path('data.php'));

        $this->assertStringContainsString(
            "env('SPATIE_DATA_CASTER_MODE', 'production')",
            $configContent,
            "config/data.php doit utiliser 'production' comme fallback du caster mode (silencieux par défaut).",
        );
    }

    #[Test]
    public function env_example_documents_caster_mode_production_as_default(): void
    {
        // Anti-régression · .env.example doit suggérer
        // SPATIE_DATA_CASTER_MODE=production car ce fichier est souvent
        // copié comme base prod. L'override dev se fait dans le .env local.

        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^SPATIE_DATA_CASTER_MODE=production$/m',
            $envExample,
            '.env.example doit poser SPATIE_DATA_CASTER_MODE=production par défaut (override en dev local).',
        );
    }
}
