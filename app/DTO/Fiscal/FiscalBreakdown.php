<?php

declare(strict_types=1);

namespace App\DTO\Fiscal;

use App\Data\User\Fiscal\FiscalBreakdownData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Services\Fiscal\FiscalCalculator;

/**
 * Detailed result of a fiscal computation for a (vehicle, company) pair
 * over a given number of days.
 *
 * Internal DTO produced by {@see FiscalCalculator} for the planning
 * drawer tax preview. Other consumers (`FleetFiscalAggregator`, …) work
 * directly against `PipelineResult` through the `FiscalSegmentedExecutor`.
 *
 * Amounts are expressed in euros with two decimals (commercial half-up
 * rounding). Convert to {@see FiscalBreakdownData::fromBreakdown()} when
 * exposing to the frontend.
 */
final readonly class FiscalBreakdown
{
    /**
     * @param  list<AppliedExemption>  $appliedExemptions  Exemptions applied to the result.
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
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public float $pollutantsDue,
        public float $totalDue,
        public array $appliedExemptions,
    ) {}
}
