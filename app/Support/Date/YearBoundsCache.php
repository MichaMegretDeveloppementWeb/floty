<?php

declare(strict_types=1);

namespace App\Support\Date;

use Carbon\CarbonImmutable;

/**
 * Process-local cache for year-boundary `CarbonImmutable` instances
 * (January 1st and December 31st of a given year).
 *
 * The cache spares the cost of re-creating identical instances in hot
 * paths that iterate over a full calendar year (Dashboard stats,
 * billing calculator, fiscal pipeline…). Scope is the current process
 * only · there is no shared cache layer.
 */
final class YearBoundsCache
{
    /** @var array<int, CarbonImmutable> */
    private static array $startCache = [];

    /** @var array<int, CarbonImmutable> */
    private static array $endCache = [];

    /**
     * Return January 1st 00:00:00 of the given year.
     */
    public static function start(int $year): CarbonImmutable
    {
        return self::$startCache[$year] ??= CarbonImmutable::create($year, 1, 1, 0, 0, 0);
    }

    /**
     * Return December 31st 23:59:59 of the given year.
     */
    public static function end(int $year): CarbonImmutable
    {
        return self::$endCache[$year] ??= CarbonImmutable::create($year, 12, 31, 23, 59, 59);
    }

    /**
     * Reset both caches. Useful in tests that rely on a fresh instance
     * identity across cases.
     */
    public static function flush(): void
    {
        self::$startCache = [];
        self::$endCache = [];
    }
}
