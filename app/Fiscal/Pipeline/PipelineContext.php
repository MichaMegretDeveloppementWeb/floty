<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;

/**
 * État accumulé pendant l'exécution d'un calcul fiscal.
 *
 * Immuable : chaque règle reçoit le contexte courant et retourne une
 * nouvelle instance via les méthodes `with*()`. Le pipeline garde la
 * trace de la dernière instance et la passe à l'étape suivante.
 *
 * Les champs `?type $foo = null` représentent des données calculées au
 * fil du pipeline. Un champ encore `null` à l'étape de tarification
 * indique soit qu'il n'y a pas eu de classification (cas dégénéré),
 * soit que la règle correspondante n'est pas encore exécutée.
 *
 * @phpstan-import-type FiscalRuleCode from \App\Fiscal\Pipeline\PipelineResult
 */
final readonly class PipelineContext
{
    /**
     * @param  list<ExemptionVerdict>  $exemptionVerdicts  Verdicts collectés étape 4
     * @param  list<string>  $appliedRuleCodes  Trace pour le snapshot PDF
     */
    public function __construct(
        public Vehicle $vehicle,
        public int $fiscalYear,
        public int $daysInYear,
        public int $daysAssignedToCompany,
        public int $cumulativeDaysForPair,
        public ?VehicleFiscalCharacteristics $currentFiscalCharacteristics = null,
        public ?HomologationMethod $resolvedCo2Method = null,
        public ?PollutantCategory $resolvedPollutantCategory = null,
        public ?float $co2FullYearTariff = null,
        public ?float $pollutantsFullYearTariff = null,
        public ?float $co2Due = null,
        public ?float $pollutantsDue = null,
        public array $exemptionVerdicts = [],
        public array $appliedRuleCodes = [],
    ) {}

    public function withCurrentFiscalCharacteristics(VehicleFiscalCharacteristics $vfc): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withResolvedCo2Method(HomologationMethod $method): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withResolvedPollutantCategory(PollutantCategory $category): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $category,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withCo2FullYearTariff(float $tariff): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $tariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withPollutantsFullYearTariff(float $tariff): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $tariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withDueAmounts(float $co2Due, float $pollutantsDue): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $co2Due,
            pollutantsDue: $pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withExemptionVerdict(ExemptionVerdict $verdict): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: [...$this->exemptionVerdicts, $verdict],
            appliedRuleCodes: $this->appliedRuleCodes,
        );
    }

    public function withAppliedRule(string $ruleCode): self
    {
        return new self(
            vehicle: $this->vehicle,
            fiscalYear: $this->fiscalYear,
            daysInYear: $this->daysInYear,
            daysAssignedToCompany: $this->daysAssignedToCompany,
            cumulativeDaysForPair: $this->cumulativeDaysForPair,
            currentFiscalCharacteristics: $this->currentFiscalCharacteristics,
            resolvedCo2Method: $this->resolvedCo2Method,
            resolvedPollutantCategory: $this->resolvedPollutantCategory,
            co2FullYearTariff: $this->co2FullYearTariff,
            pollutantsFullYearTariff: $this->pollutantsFullYearTariff,
            co2Due: $this->co2Due,
            pollutantsDue: $this->pollutantsDue,
            exemptionVerdicts: $this->exemptionVerdicts,
            appliedRuleCodes: [...$this->appliedRuleCodes, $ruleCode],
        );
    }
}
