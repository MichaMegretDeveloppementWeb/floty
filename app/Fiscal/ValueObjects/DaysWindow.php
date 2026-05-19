<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use Carbon\CarbonImmutable;

/**
 * Inclusive `[start, end]` day window used to restrict the day counter
 * in the fiscal pipeline.
 *
 * Set by {@see FiscalSegmentedExecutor} on the
 * {@see PipelineContext} when a calculation is
 * segmented by VFC: each sub-calculation receives the VFC segment's
 * window but still sees the full contracts (so per-contract rules like
 * R-2024-021 LCD judge on the contract's total duration, not on the
 * clipped portion).
 *
 * R-2024-002 (DailyProrata) intersects its day enumeration with this
 * window when set.
 */
final readonly class DaysWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * True iff the date is inside the window (inclusive bounds),
     * compared at day granularity.
     */
    public function contains(CarbonImmutable $date): bool
    {
        $day = $date->startOfDay();

        return ! $day->lessThan($this->start->startOfDay())
            && ! $day->greaterThan($this->end->startOfDay());
    }
}
