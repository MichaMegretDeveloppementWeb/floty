<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\DTO\Fiscal\FiscalBreakdown;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\PipelineResult;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Shared\Fiscal\FiscalYearContext;

/**
 * Legacy facade over the fiscal engine, kept for the planning preview
 * call site. Delegates to {@see FiscalSegmentedExecutor}, which runs
 * the ADR-0006 pipeline across the VFC × Rules cartesian product, and
 * adapts the {@see PipelineResult} into the consumer-facing
 * {@see FiscalBreakdown}.
 */
final readonly class FiscalCalculator
{
    /**
     * Historic LCD threshold exposed for downstream UI consumers. The
     * authoritative logic lives in {@see R2024_021_ShortTermRental}.
     */
    public const int LCD_THRESHOLD_DAYS = 30;

    public function __construct(
        private FiscalSegmentedExecutor $executor,
        private FiscalYearContext $yearContext,
    ) {}

    /**
     * @param  list<Contract>  $contractsForPair  Active contracts for the pair within the year
     * @param  list<Unavailability>  $vehicleUnavailabilities  Vehicle unavailabilities within the year (R-2024-008)
     */
    public function calculate(
        Vehicle $vehicle,
        array $contractsForPair,
        array $vehicleUnavailabilities,
        int $fiscalYear,
    ): FiscalBreakdown {
        $context = new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: $fiscalYear,
            daysInYear: $this->yearContext->daysInYear($fiscalYear),
            contractsForPair: $contractsForPair,
            vehicleUnavailabilitiesInYear: $vehicleUnavailabilities,
        );

        return $this->toBreakdown($this->executor->execute($context));
    }

    private function toBreakdown(PipelineResult $result): FiscalBreakdown
    {
        return new FiscalBreakdown(
            daysAssigned: $result->daysAssigned,
            cumulativeDaysForPair: $result->cumulativeDaysForPair,
            daysInYear: $result->daysInYear,
            lcdExempt: $result->lcdExempt,
            electricExempt: $result->electricExempt,
            handicapExempt: $result->handicapExempt,
            co2Method: $result->co2Method,
            co2FullYearTariff: $result->co2FullYearTariff,
            co2Due: $result->co2Due,
            pollutantCategory: $result->pollutantCategory,
            pollutantsFullYearTariff: $result->pollutantsFullYearTariff,
            pollutantsDue: $result->pollutantsDue,
            totalDue: $result->totalDue,
            appliedExemptions: $result->appliedExemptions,
        );
    }
}
