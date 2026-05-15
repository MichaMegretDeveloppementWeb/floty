<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que `composer.json` déclare les extensions PHP runtime requises.
 *
 * Doctrine · plan-remédiation Vague 1 Lot 6 D13 (F-33-007) · `composer.json`
 * doit déclarer `ext-pdo` + `ext-pdo_mysql` pour que `composer install` /
 * `composer check-platform-reqs` plantent immédiatement sur un serveur où
 * ces extensions manquent · au lieu d'un crash mystérieux à la première
 * requête DB en runtime.
 *
 * Test inspection statique · on lit `composer.json` directement plutôt que
 * d'introspecter Composer runtime (les require platform sont consommés
 * à l'install/update, pas à l'exécution).
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
            "composer.json doit déclarer 'ext-pdo_mysql' dans require · le driver MySQL est consommé par config/database.php (cf. F-33-007).",
        );
    }

    #[Test]
    public function composer_json_pins_php_85_minimum(): void
    {
        // Garde-fou couplé · `config/database.php` utilise `\Pdo\Mysql`
        // (PHP 8.5+). Si quelqu'un descend la contrainte à PHP 8.4,
        // l'import déclenchera une erreur d'analyse statique.

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
