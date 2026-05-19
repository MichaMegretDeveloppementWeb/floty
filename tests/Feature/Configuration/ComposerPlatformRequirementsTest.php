<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que `composer.json` déclare `ext-pdo` + `ext-pdo_mysql` et impose
 * PHP 8.5+ (extensions runtime requises pour MySQL).
 */
final class ComposerPlatformRequirementsTest extends TestCase
{
    #[Test]
    public function composer_json_requires_pdo_and_pdo_mysql_extensions(): void
    {
        $composer = json_decode(
            (string) file_get_contents(base_path('composer.json')),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $require = $composer['require'] ?? [];

        self::assertArrayHasKey(
            'ext-pdo',
            $require,
            "composer.json doit déclarer 'ext-pdo' dans require pour échouer rapidement à l'install si l'extension manque.",
        );

        self::assertArrayHasKey(
            'ext-pdo_mysql',
            $require,
            "composer.json doit déclarer 'ext-pdo_mysql' dans require · le driver MySQL est consommé par config/database.php.",
        );
    }

    #[Test]
    public function composer_json_pins_php_85_minimum(): void
    {
        $composer = json_decode(
            (string) file_get_contents(base_path('composer.json')),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $phpConstraint = $composer['require']['php'] ?? null;

        self::assertSame(
            '^8.5',
            $phpConstraint,
            "composer.json doit imposer 'php: ^8.5' · `\\Pdo\\Mysql` (config/database.php) n'existe pas en PHP 8.4.",
        );
    }
}
