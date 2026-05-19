<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineResult;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;

/**
 * Detail of one pipeline execution for a sub-segment of the cartesian
 * product {VFC × rules}.
 *
 * Emitted by
 * {@see FiscalSegmentedExecutor::executeWithSegments()}.
 *
 * Field semantics:
 * - `start` / `end`: inclusive bounds of the intersection between the
 *   VFC segment and the rule segment. The pipeline ran on this window.
 * - `vfcSegment`: VFC active over the interval (used to expose vehicle
 *   characteristics to the UI).
 * - `ruleSegment`: rules applicable over the interval (for audit and
 *   "which rules produced this amount" exposure).
 * - `result`: partial `PipelineResult` (raw CO₂ / raw pollutants
 *   prorated over the window). The annual total is rebuilt by summing
 *   `co2DueRaw` + `pollutantsDueRaw` then rounding once (R-2024-003).
 *
 * Lets consumers (typically
 * {@see FleetFiscalAggregator::vehicleFullYearTaxBreakdown()})
 * expose a per-sub-segment tariff detail in the UI: full-year tariff,
 * prorated amounts, segment-specific exemptions.
 */
final readonly class FiscalSegmentBreakdown
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public VfcEffectiveSegment $vfcSegment,
        public RuleEffectiveSegment $ruleSegment,
        public PipelineResult $result,
    ) {}

    /**
     * Number of days in the intersection (inclusive bounds, day
     * granularity).
     */
    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }
}
