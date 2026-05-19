<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use stdClass;

/**
 * Deterministic SHA-256 hash of a fiscal declaration snapshot.
 *
 * The hash describes the fiscal content (canonical JSON snapshot), not
 * the PDF binary (a PDF cannot embed its own hash). The same value
 * appears both in the PDF and on the declaration Show page; any third
 * party with the persisted snapshot can recompute and verify it.
 *
 * Aligned with the immutability doctrine (ADR-0008 / ADR-0015) · the
 * snapshot is frozen at generation time (`markAsGenerated`) and stored
 * in the JSON column `generated_snapshot_payload`. Any post-generation
 * change would invalidate the hash, guaranteeing documentary integrity.
 *
 * Canonicalisation ·
 *   1. Recursive ksort so an identical payload always yields the same
 *      key order.
 *   2. Recursive `[]` → empty `stdClass` normalisation so PHP empty
 *      arrays and JSON empty objects hash identically.
 *   3. `json_encode` with `JSON_UNESCAPED_UNICODE |
 *      JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`.
 *
 * Static intra-request memoization keyed by the canonical JSON avoids
 * recomputing SHA-256 when multiple Spatie hydrations of
 * `FiscalDeclarationData::fromModel` happen in the same HTTP request
 * (Show pages that load many historical declarations). The cache is
 * worker-scoped, no cross-request bleed. {@see flush()} resets it for
 * tests that need isolation.
 */
final class SnapshotHashCalculator
{
    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function compute(array $payload): string
    {
        $canonical = self::canonicalize($payload);
        $json = json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return self::$cache[$json] ??= hash('sha256', $json);
    }

    /**
     * Flushes the static cache. Used by tests that assert on
     * memoization or need full per-case isolation.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Recursive canonicalisation ·
     *   - empty array → empty stdClass (PHP/JSON parity)
     *   - associative array → sorted by key + recursion
     *   - list array → recursion on elements (order preserved)
     *   - scalar → unchanged
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new stdClass;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        $normalized = [];
        foreach ($value as $key => $sub) {
            $normalized[$key] = self::canonicalize($sub);
        }

        return $normalized;
    }
}
