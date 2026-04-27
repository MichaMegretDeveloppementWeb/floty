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
        public ?bool $isFiscallyTaxable = null,
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
        return $this->copyWith(['currentFiscalCharacteristics' => $vfc]);
    }

    public function withIsFiscallyTaxable(bool $taxable): self
    {
        return $this->copyWith(['isFiscallyTaxable' => $taxable]);
    }

    public function withResolvedCo2Method(HomologationMethod $method): self
    {
        return $this->copyWith(['resolvedCo2Method' => $method]);
    }

    public function withResolvedPollutantCategory(PollutantCategory $category): self
    {
        return $this->copyWith(['resolvedPollutantCategory' => $category]);
    }

    public function withCo2FullYearTariff(float $tariff): self
    {
        return $this->copyWith(['co2FullYearTariff' => $tariff]);
    }

    public function withPollutantsFullYearTariff(float $tariff): self
    {
        return $this->copyWith(['pollutantsFullYearTariff' => $tariff]);
    }

    public function withDueAmounts(float $co2Due, float $pollutantsDue): self
    {
        return $this->copyWith(['co2Due' => $co2Due, 'pollutantsDue' => $pollutantsDue]);
    }

    public function withExemptionVerdict(ExemptionVerdict $verdict): self
    {
        return $this->copyWith(['exemptionVerdicts' => [...$this->exemptionVerdicts, $verdict]]);
    }

    public function withAppliedRule(string $ruleCode): self
    {
        return $this->copyWith(['appliedRuleCodes' => [...$this->appliedRuleCodes, $ruleCode]]);
    }

    /**
     * Helper interne qui clone l'instance en remplaçant les champs
     * fournis. Évite la répétition des 14+ champs à chaque méthode
     * `with*()`. Un nouveau champ ajouté au constructeur ne nécessite
     * aucune modification ici.
     *
     * Utilise `array_key_exists` (pas `??`) pour autoriser les valeurs
     * falsy intentionnelles (`false`, `0`, `null`).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function copyWith(array $overrides): self
    {
        $pick = fn (string $key, mixed $current): mixed => array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $current;

        return new self(
            vehicle: $pick('vehicle', $this->vehicle),
            fiscalYear: $pick('fiscalYear', $this->fiscalYear),
            daysInYear: $pick('daysInYear', $this->daysInYear),
            daysAssignedToCompany: $pick('daysAssignedToCompany', $this->daysAssignedToCompany),
            cumulativeDaysForPair: $pick('cumulativeDaysForPair', $this->cumulativeDaysForPair),
            currentFiscalCharacteristics: $pick('currentFiscalCharacteristics', $this->currentFiscalCharacteristics),
            isFiscallyTaxable: $pick('isFiscallyTaxable', $this->isFiscallyTaxable),
            resolvedCo2Method: $pick('resolvedCo2Method', $this->resolvedCo2Method),
            resolvedPollutantCategory: $pick('resolvedPollutantCategory', $this->resolvedPollutantCategory),
            co2FullYearTariff: $pick('co2FullYearTariff', $this->co2FullYearTariff),
            pollutantsFullYearTariff: $pick('pollutantsFullYearTariff', $this->pollutantsFullYearTariff),
            co2Due: $pick('co2Due', $this->co2Due),
            pollutantsDue: $pick('pollutantsDue', $this->pollutantsDue),
            exemptionVerdicts: $pick('exemptionVerdicts', $this->exemptionVerdicts),
            appliedRuleCodes: $pick('appliedRuleCodes', $this->appliedRuleCodes),
        );
    }
}
