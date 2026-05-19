<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que `config('data.var_dumper_caster_mode')` a un défaut sûr
 * (`'production'` silencieux, pas `'development'` verbeux).
 */
final class DataCasterModeTest extends TestCase
{
    #[Test]
    public function config_data_caster_mode_falls_back_to_production_when_env_absent(): void
    {
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
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^SPATIE_DATA_CASTER_MODE=production$/m',
            $envExample,
            '.env.example doit poser SPATIE_DATA_CASTER_MODE=production par défaut (override en dev local).',
        );
    }
}
