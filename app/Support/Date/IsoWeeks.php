<?php

declare(strict_types=1);

namespace App\Support\Date;

use Carbon\CarbonImmutable;

/**
 * ISO-8601 week helpers tailored to the planning heatmap.
 *
 * Every year is rendered as exactly **53 cells** (= 53 weeks of 7 days):
 *   - cell 1 covers the Monday-to-Sunday week containing January 1st of
 *     the year (may start in December of Y-1 when January 1st is not a
 *     Monday);
 *   - cell 53 covers the week containing December 31st of the year
 *     (may end in January of Y+1);
 *   - every cell has 7 days and at least one of them falls inside the
 *     year; a week that straddles two years appears in both heatmaps,
 *     with day-level filtering applied at the density step.
 *
 * For any year there are always exactly 53 Mondays in
 * `[monday_of_week(1/1), monday_of_week(31/12)]`, so the cell count is
 * always constant. This replaces the strict ISO convention (52 or 53
 * cells) which left the last 1-3 days of years like 2024/2025 invisible.
 */
final class IsoWeeks
{
    /**
     * Number of cells in a heatmap year.
     */
    public const CELLS_PER_YEAR = 53;

    /**
     * Monday of cell 1 of the heatmap for the given year — the ISO week
     * containing January 1st (may fall in December of Y-1).
     */
    public static function cellOriginForYear(int $year): CarbonImmutable
    {
        $jan1 = CarbonImmutable::create($year, 1, 1);

        return $jan1->subDays((int) $jan1->dayOfWeekIso - 1);
    }

    /**
     * Return the 53 Mondays representing each cell of the heatmap for
     * the given year.
     *
     * @return list<CarbonImmutable>
     */
    public static function cellsForYear(int $year): array
    {
        $origin = self::cellOriginForYear($year);
        $cells = [];
        for ($i = 0; $i < self::CELLS_PER_YEAR; $i++) {
            $cells[] = $origin->addDays($i * 7);
        }

        return $cells;
    }

    /**
     * Return the cell index (1..53) containing the given date in the
     * heatmap of the given year, or `null` when the date sits outside
     * the grid.
     */
    public static function cellIndexForDate(int $year, CarbonImmutable $date): ?int
    {
        $origin = self::cellOriginForYear($year);
        $daysSinceOrigin = (int) $origin->diffInDays($date, false);
        if ($daysSinceOrigin < 0) {
            return null;
        }
        $idx = (int) floor($daysSinceOrigin / 7) + 1;

        return $idx >= 1 && $idx <= self::CELLS_PER_YEAR ? $idx : null;
    }

    /**
     * Number of ISO weeks in the given year (52 or 53). Kept for legacy
     * usages; the planning heatmap now relies on
     * {@see static::cellsForYear} (always 53 cells).
     */
    public static function inYear(int $year): int
    {
        return (int) CarbonImmutable::create($year, 12, 28)->isoWeek;
    }
}
