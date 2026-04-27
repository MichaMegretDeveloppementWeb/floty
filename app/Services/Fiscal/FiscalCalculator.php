<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\DTO\Fiscal\FiscalBreakdown;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\PipelineResult;
use App\Fiscal\Year2024\Exemption\R2024_021_LowDayCount;
use App\Models\Vehicle;
use App\Services\Shared\Fiscal\FiscalYearContext;

/**
 * Façade legacy du moteur fiscal Floty.
 *
 * Préserve l'API utilisée par {@see WeekDetailService} et
 * {@see FleetFiscalAggregator} (signature `calculate(Vehicle, int, int,
 * int): FiscalBreakdown`) tout en déléguant l'exécution réelle au
 * pipeline ADR-0006 (`FiscalPipeline`). La sortie est convertie depuis
 * un {@see PipelineResult} vers le DTO interne {@see FiscalBreakdown}
 * que les consommateurs connaissent déjà.
 *
 * Cette façade existe pour limiter le rayon de blast de la refonte
 * 1.8 — la migration des consommateurs vers le pipeline natif se fera
 * progressivement aux phases métier 10+.
 */
final readonly class FiscalCalculator
{
    /**
     * Seuil LCD historique exposé pour les consommateurs externes (UI
     * notamment). La logique est désormais portée par
     * {@see R2024_021_LowDayCount}.
     */
    public const int LCD_THRESHOLD_DAYS = 30;

    public function __construct(
        private FiscalPipeline $pipeline,
        private FiscalYearContext $yearContext,
    ) {}

    public function calculate(
        Vehicle $vehicle,
        int $daysAssignedToCompany,
        int $cumulativeDaysForPair,
        int $fiscalYear,
    ): FiscalBreakdown {
        $context = new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: $fiscalYear,
            daysInYear: $this->yearContext->daysInYear($fiscalYear),
            daysAssignedToCompany: $daysAssignedToCompany,
            cumulativeDaysForPair: $cumulativeDaysForPair,
        );

        return $this->toBreakdown($this->pipeline->execute($context));
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
            exemptionReasons: $result->exemptionReasons,
        );
    }
}
