<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Fiscal;

use App\Services\Fiscal\SnapshotHashCalculator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

/**
 * Phase 13 D5.10.J · garantit le déterminisme de l'empreinte fiscale
 * affichée à l'identique sur le PDF et la page Show. L'invariance par
 * réordonnancement est la propriété clé · sans elle, deux processus de
 * sérialisation pourraient produire des hash différents pour le même
 * snapshot sémantique.
 */
final class SnapshotHashCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Lot 5 D8 · isole chaque cas du cache statique pour pouvoir
        // tester la mémoïsation et éviter toute contamination entre tests.
        SnapshotHashCalculator::flush();
    }

    #[Test]
    public function compute_returns_a_sha256_hex_string(): void
    {
        $hash = SnapshotHashCalculator::compute(['fiscalYear' => 2025]);

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function compute_is_deterministic_for_identical_payloads(): void
    {
        $payload = [
            'fiscalYear' => 2025,
            'companyShortCode' => 'ACME',
            'totalDue' => 1234.56,
        ];

        $this->assertSame(
            SnapshotHashCalculator::compute($payload),
            SnapshotHashCalculator::compute($payload),
        );
    }

    #[Test]
    public function compute_is_invariant_under_key_reordering(): void
    {
        $a = [
            'fiscalYear' => 2025,
            'companyShortCode' => 'ACME',
            'totalDue' => 1234.56,
        ];
        $b = [
            'totalDue' => 1234.56,
            'companyShortCode' => 'ACME',
            'fiscalYear' => 2025,
        ];

        $this->assertSame(
            SnapshotHashCalculator::compute($a),
            SnapshotHashCalculator::compute($b),
        );
    }

    #[Test]
    public function compute_is_invariant_under_nested_key_reordering(): void
    {
        $a = [
            'fiscalYear' => 2025,
            'contractBreakdown' => [
                [
                    'startDate' => '2025-01-01',
                    'endDate' => '2025-06-30',
                    'vehicleLabel' => 'AA-001-AA',
                ],
            ],
        ];
        $b = [
            'contractBreakdown' => [
                [
                    'vehicleLabel' => 'AA-001-AA',
                    'endDate' => '2025-06-30',
                    'startDate' => '2025-01-01',
                ],
            ],
            'fiscalYear' => 2025,
        ];

        $this->assertSame(
            SnapshotHashCalculator::compute($a),
            SnapshotHashCalculator::compute($b),
        );
    }

    #[Test]
    public function compute_changes_when_a_value_changes(): void
    {
        $base = [
            'fiscalYear' => 2025,
            'totalDue' => 1234.56,
        ];
        $mutated = [
            'fiscalYear' => 2025,
            'totalDue' => 1234.57,
        ];

        $this->assertNotSame(
            SnapshotHashCalculator::compute($base),
            SnapshotHashCalculator::compute($mutated),
        );
    }

    #[Test]
    public function compute_handles_empty_payload(): void
    {
        // Lot 5 D8 (F-19D2-011) · un payload vide est canonicalisé en
        // `(object) {}` plutôt qu'en `[]` JSON (cohérence PHP-vs-JSON ·
        // un array PHP vide et un stdClass vide doivent produire le
        // même hash).
        $hash = SnapshotHashCalculator::compute([]);

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', '{}'), $hash);
    }

    #[Test]
    public function compute_preserves_unicode_characters(): void
    {
        $payload = ['companyLegalName' => 'Société Générale & Cie'];
        $hash = SnapshotHashCalculator::compute($payload);

        $expected = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->assertSame($expected, $hash);
    }

    #[Test]
    public function compute_normalizes_nested_empty_arrays_as_empty_objects(): void
    {
        // Lot 5 D8 (F-19D2-011) · ferme le bord PHP-vs-JSON · sections
        // optionnelles vides (clusters: [], obsoleteReasons: [], etc.)
        // doivent produire le même hash quel que soit le pipeline qui
        // les fournit (array PHP vide ou stdClass vide).
        $withArrays = [
            'fiscalYear' => 2025,
            'clusters' => [],
            'breakdown' => [
                'lines' => [],
            ],
        ];
        $withObjects = [
            'fiscalYear' => 2025,
            'clusters' => new stdClass,
            'breakdown' => [
                'lines' => new stdClass,
            ],
        ];

        $this->assertSame(
            SnapshotHashCalculator::compute($withArrays),
            SnapshotHashCalculator::compute($withObjects),
        );
    }

    #[Test]
    public function compute_preserves_list_arrays_as_arrays(): void
    {
        // Lot 5 D8 · garde-fou · les listes non-vides ne doivent PAS être
        // converties en objet (sinon on casserait l'ordre signifiant
        // des breakdowns, lignes, motifs etc.).
        $payload = [
            'lines' => [
                ['amount' => 100],
                ['amount' => 200],
            ],
        ];

        $hash = SnapshotHashCalculator::compute($payload);
        $expected = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->assertSame($expected, $hash);
    }

    #[Test]
    public function compute_uses_static_cache_for_identical_payloads(): void
    {
        // Lot 5 D8 (F-19D2-007) · le cache statique évite de recalculer
        // SHA-256 sur le même payload (utile quand `FiscalDeclarationData
        // ::fromModel` hydrate N déclarations historiques dans la même
        // requête HTTP). On vérifie via reflection la taille du cache.
        $payload = ['fiscalYear' => 2025, 'totalDue' => 1234.56];

        $hash1 = SnapshotHashCalculator::compute($payload);
        $hash2 = SnapshotHashCalculator::compute($payload);

        $this->assertSame($hash1, $hash2);

        $reflection = new ReflectionClass(SnapshotHashCalculator::class);
        $cacheProperty = $reflection->getProperty('cache');
        $cache = $cacheProperty->getValue();

        $this->assertCount(1, $cache, 'Le cache doit avoir une seule entrée pour 2 appels identiques.');
    }

    #[Test]
    public function flush_resets_the_static_cache(): void
    {
        SnapshotHashCalculator::compute(['a' => 1]);
        SnapshotHashCalculator::compute(['b' => 2]);

        $reflection = new ReflectionClass(SnapshotHashCalculator::class);
        $cacheProperty = $reflection->getProperty('cache');

        $this->assertCount(2, $cacheProperty->getValue());

        SnapshotHashCalculator::flush();

        $this->assertCount(0, $cacheProperty->getValue());
    }
}
