<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Structured output of a fiscal calculation (ADR-0006 § 2 step 8).
 *
 * This internal DTO serves as both the public return of
 * {@see FiscalPipeline::execute()} and the pivot for conversion to the
 * presentation DTO `FiscalBreakdown`.
 *
 * `appliedRuleCodes` lets PDF snapshots reference which rules
 * participated in the calculation (ADR-0006 § 5 + ADR-0009 · no
 * version, just the rule code).
 *
 * @phpstan-type FiscalRuleCode string
 */
final readonly class PipelineResult
{
    /**
     * `co2Due`, `pollutantsDue`, `totalDue` are rounded half-up to 2
     * decimals for per-pair display (PDF row, planning drawer). The
     * `*Raw` fields carry the pre-rounding values used by
     * {@see FleetFiscalAggregator} to apply R-2024-003 (one rounding
     * per taxpayer at the company level).
     *
     * @param  list<AppliedExemption>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     */
    public function __construct(
        public int $daysAssigned,
        public int $cumulativeDaysForPair,
        public int $daysInYear,
        public bool $lcdExempt,
        public bool $electricExempt,
        public bool $handicapExempt,
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public float $co2Due,
        public float $co2DueRaw,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public float $pollutantsDue,
        public float $pollutantsDueRaw,
        public float $totalDue,
        public array $appliedExemptions,
        public array $appliedRuleCodes,
    ) {}
}
