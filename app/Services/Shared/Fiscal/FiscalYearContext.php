<?php

declare(strict_types=1);

namespace App\Services\Shared\Fiscal;

use App\Fiscal\Registry\FiscalRuleRegistry;

/**
 * Calendar reference for a fiscal year (days in year, supported flag).
 *
 * The single source of authority for supported years is the
 * {@see FiscalRuleRegistry} (years with at least one registered rule).
 * Stateless and immutable, safe to share as a singleton.
 */
final class FiscalYearContext
{
    public function __construct(
        private readonly FiscalRuleRegistry $registry,
    ) {}

    /**
     * 366 for leap years, 365 otherwise. Single source for backend prorata.
     */
    public function daysInYear(int $year): int
    {
        return $this->isLeapYear($year) ? 366 : 365;
    }

    /**
     * True when at least one fiscal rule is registered for the year.
     */
    public function isSupported(int $year): bool
    {
        return in_array($year, $this->registry->registeredYears(), true);
    }

    /**
     * True when every year in the inclusive `[startYear, endYear]` range
     * has coded fiscal rules. Used to qualify whether a multi-year
     * contract is fully tariffable.
     */
    public function rangeSupported(int $startYear, int $endYear): bool
    {
        for ($year = $startYear; $year <= $endYear; $year++) {
            if (! $this->isSupported($year)) {
                return false;
            }
        }

        return true;
    }

    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }
}
