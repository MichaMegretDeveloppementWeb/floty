<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Fiscal;

use App\Services\Fiscal\SnapshotHashCalculator;
use PHPUnit\Framework\Attributes\Test;
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
        $hash = SnapshotHashCalculator::compute([]);

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', '[]'), $hash);
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
}
