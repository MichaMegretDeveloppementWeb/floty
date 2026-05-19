<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Garantit l'isolation stricte du namespace `App\Fiscal\Year2025` :
 * aucune classe du catalogue 2025 ne doit importer une classe
 * `App\Fiscal\Year2024\*` (ADR-0022).
 */
final class Year2025IsolationTest extends TestCase
{
    #[Test]
    public function aucun_fichier_year2025_n_importe_year2024(): void
    {
        $year2025Dir = dirname(__DIR__, 4).'/app/Fiscal/Year2025';

        self::assertDirectoryExists($year2025Dir);

        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($year2025Dir),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getRealPath());

            if ($contents === false) {
                continue;
            }

            if (preg_match_all(
                '/use\s+App\\\\Fiscal\\\\Year2024\\\\[\w\\\\]+;/',
                $contents,
                $matches,
            )) {
                $violations[$file->getFilename()] = $matches[0];
            }
        }

        self::assertEmpty(
            $violations,
            'Violation ADR-0022 : classes 2025 important du 2024 : '
            .json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    #[Test]
    public function aucun_fichier_year2024_n_importe_year2025(): void
    {
        // Contrôle symétrique : le catalogue 2024 ne doit pas connaître
        // 2025 (ADR-0009).
        $year2024Dir = dirname(__DIR__, 4).'/app/Fiscal/Year2024';

        self::assertDirectoryExists($year2024Dir);

        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($year2024Dir),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getRealPath());

            if ($contents === false) {
                continue;
            }

            if (preg_match_all(
                '/use\s+App\\\\Fiscal\\\\Year2025\\\\[\w\\\\]+;/',
                $contents,
                $matches,
            )) {
                $violations[$file->getFilename()] = $matches[0];
            }
        }

        self::assertEmpty(
            $violations,
            'Violation ADR-0009 : classes 2024 important du 2025 : '
            .json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
