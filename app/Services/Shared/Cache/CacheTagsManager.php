<?php

declare(strict_types=1);

namespace App\Services\Shared\Cache;

use App\Exceptions\Cache\CacheTagsException;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Emulates cache tags on top of the `database` driver (ADR-0008).
 *
 * Hostinger Business offers neither Redis nor Memcached; Laravel tags
 * are not supported on the `database` driver natively. Tagging is
 * emulated through hierarchical colon-separated prefixes ·
 *
 *   vehicle:42:fiscal_characteristics
 *   vehicle:42:current_contracts
 *   vehicle:42:lcd_cumul:acme:2024
 *
 * Invalidating "everything about vehicle 42" deletes every row whose
 * key starts with `vehicle:42:`. Use {@see key()} to compose keys and
 * {@see invalidateByPrefix()} to invalidate.
 *
 * Guarantees ·
 *   - `invalidateByPrefix('vehicle:42')` deletes the exact key
 *     `vehicle:42` AND its descendants `vehicle:42:*`, but never a
 *     sibling such as `vehicle:420:*` (frontier `:` is appended).
 *   - LIKE metacharacters (`%`, `_`, `\`) in the prefix are escaped.
 *   - The Laravel cache store prefix is applied at the SQL layer.
 *
 * Future Redis migration · downstream services can switch to
 * `Cache::tags([...])` directly; keys produced by {@see key()} remain
 * Redis-compatible.
 *
 * R3 exemption (ADR-0013) · this manager performs direct SQL via
 * `$store->getConnection()->table()->delete()` because it is pure
 * infrastructure (cache store maintenance, not a business entity),
 * which the Repository layer is not meant to model.
 */
final class CacheTagsManager
{
    private const DEFAULT_STORE = 'database';

    public function __construct(
        private readonly CacheManager $cache,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Composes a cache key from colon-joined segments, ensuring the
     * hierarchy stays compatible with {@see invalidateByPrefix()}.
     */
    public function key(string|int ...$parts): string
    {
        if ($parts === []) {
            throw CacheTagsException::keyRequiresAtLeastOneSegment();
        }

        return implode(':', array_map(static fn (string|int $part) => (string) $part, $parts));
    }

    /**
     * Deletes the exact key matching the prefix plus all descendants.
     *
     * @return int Number of deleted rows, useful for logging and tests.
     */
    public function invalidateByPrefix(string $logicalPrefix): int
    {
        $store = $this->resolveDatabaseStore();
        $table = $this->config->get('cache.stores.'.self::DEFAULT_STORE.'.table', 'cache');

        $normalized = rtrim($logicalPrefix, ':');
        $exactKey = $store->getPrefix().$normalized;
        $descendantsPattern = $this->escapeLikeLiteral($exactKey.':').'%';

        return $store->getConnection()
            ->table($table)
            ->where(function ($query) use ($exactKey, $descendantsPattern): void {
                $query
                    ->where('key', '=', $exactKey)
                    ->orWhere('key', 'like', $descendantsPattern);
            })
            ->delete();
    }

    /**
     * Returns the underlying {@see DatabaseStore} or throws if the
     * `database` store is misconfigured (otherwise we would silently
     * invalidate nothing).
     */
    private function resolveDatabaseStore(): DatabaseStore
    {
        $store = $this->cache->store(self::DEFAULT_STORE)->getStore();

        if (! $store instanceof DatabaseStore) {
            throw CacheTagsException::nonDatabaseStore($store::class);
        }

        return $store;
    }

    /**
     * Escapes MySQL LIKE metacharacters (`%`, `_`, `\`) so the prefix is
     * never interpreted as a wildcard pattern.
     */
    private function escapeLikeLiteral(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
