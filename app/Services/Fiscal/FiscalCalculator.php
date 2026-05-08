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
 * Façade legacy du moteur fiscal Floty.
 *
 * Préserve l'API utilisée par {@see WeekDetailService::previewTaxes()}
 * (signature `calculate(Vehicle, list<Contract>, list<Unavailability>,
 * int): FiscalBreakdown`) tout en déléguant l'exécution réelle au
 * {@see FiscalSegmentedExecutor} qui orchestre le pipeline ADR-0006
 * sur le produit cartésien VFC × Règles. La sortie est convertie
 * depuis un {@see PipelineResult} vers le DTO interne
 * {@see FiscalBreakdown} que les consommateurs connaissent déjà.
 *
 * **Refonte 04.F (ADR-0014)** : la signature accepte la liste des
 * contrats du couple et les indispos du véhicule au lieu de deux
 * entiers de cumul. Les règles souveraines R-2024-021 et R-2024-008
 * décident des jours exonérés.
 *
 * **Refonte κ.5** : la dépendance interne passe de `FiscalPipeline` à
 * {@see FiscalSegmentedExecutor}. Effet métier : la preview taxes du
 * drawer planning devient correcte sur véhicule multi-VFC, et bénéficie
 * automatiquement de la segmentation multi-règles (chantier κ).
 */
final readonly class FiscalCalculator
{
    /**
     * Seuil LCD historique exposé pour les consommateurs externes (UI
     * notamment). La logique est désormais portée par
     * {@see R2024_021_ShortTermRental}.
     */
    public const int LCD_THRESHOLD_DAYS = 30;

    public function __construct(
        private FiscalSegmentedExecutor $executor,
        private FiscalYearContext $yearContext,
    ) {}

    /**
     * @param  list<Contract>  $contractsForPair  Contrats actifs du couple sur l'année
     * @param  list<Unavailability>  $vehicleUnavailabilities  Indispos du véhicule sur l'année (filtrage R-2024-008)
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
