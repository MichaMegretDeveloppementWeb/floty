<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Resolves the years selectable in UI dropdowns from the actual
 * contract data, not from a static config list.
 *
 *   - `minYear()` · `MIN(YEAR(start_date))` over non-soft-deleted
 *     contracts; falls back to `currentYear()` when the table is empty.
 *   - `maxYear()` · `MAX(currentYear(), MAX(YEAR(start_date)))`, so
 *     contracts saved in advance for a future year still appear.
 *   - `availableYears()` · continuous range `[minYear, …, maxYear]`.
 *
 * Bounds are cached forever under {@see self::CACHE_KEY}; invalidation
 * is driven by `ContractObserver` on any `Contract` mutation. The
 * service is registered as a singleton in {@see AppServiceProvider}
 * (one instance per PHP worker); the cache backend (`database` in V1
 * per ADR-0008) is shared across workers and requests.
 *
 * Scope · global, not per-entity, so every selector in the app shows
 * the same year list.
 */
final class AvailableYearsResolver
{
    /**
     * Cache key for the year bounds. Stores `['min' => ?int, 'max' => ?int]`.
     */
    public const CACHE_KEY = 'floty:contracts:year_bounds';

    /**
     * Per-request memo of the resolved bounds. The resolver is a
     * singleton (one instance per request), so this avoids re-reading
     * the cache store on every `minYear()` / `maxYear()` /
     * `availableYears()` call within a single request. Cleared by
     * {@see forgetCache()} so a mid-request Contract mutation still
     * re-reads fresh bounds.
     *
     * @var array{min: int|null, max: int|null}|null
     */
    private ?array $boundsMemo = null;

    public function __construct(
        private readonly ContractReadRepositoryInterface $contracts,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Current calendar year; not cached (system clock read).
     */
    public function currentYear(): int
    {
        return (int) CarbonImmutable::now()->year;
    }

    /**
     * Global minimum year, or the current year when no contract exists.
     */
    public function minYear(): int
    {
        $bounds = $this->bounds();
        $current = $this->currentYear();

        return $bounds['min'] !== null ? min($bounds['min'], $current) : $current;
    }

    /**
     * Global maximum year, never below the current calendar year.
     */
    public function maxYear(): int
    {
        $bounds = $this->bounds();
        $current = $this->currentYear();

        return $bounds['max'] !== null ? max($bounds['max'], $current) : $current;
    }

    /**
     * Continuous `[minYear, …, maxYear]` range for UI selectors.
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        $min = $this->minYear();
        $max = $this->maxYear();

        return range($min, $max);
    }

    /**
     * Invalidates the cached bounds. Called by `ContractObserver` on
     * Contract mutations; exposed publicly for manual flushes (artisan
     * commands, exceptional flows).
     */
    public function forgetCache(): void
    {
        $this->boundsMemo = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array{min: int|null, max: int|null}
     */
    private function bounds(): array
    {
        /** @var array{min: int|null, max: int|null} */
        return $this->boundsMemo ??= $this->cache->rememberForever(
            self::CACHE_KEY,
            fn (): array => $this->contracts->yearBounds(),
        );
    }
}
