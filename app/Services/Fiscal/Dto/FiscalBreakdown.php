<?php

namespace App\Services\Fiscal\Dto;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;

/**
 * Résultat détaillé d'un calcul fiscal MVP pour un couple
 * (véhicule, entreprise utilisatrice) sur un nombre de jours donné.
 *
 * Les montants sont en euros, deux décimales (arrondi commercial half-up).
 */
final class FiscalBreakdown
{
    /**
     * @param  list<string>  $exemptionReasons  Motifs affichables en UI (FR)
     */
    public function __construct(
        public readonly int $daysAssigned,
        public readonly int $cumulativeDaysForPair,
        public readonly int $daysInYear,
        public readonly bool $lcdExempt,
        public readonly bool $electricExempt,
        public readonly bool $handicapExempt,
        public readonly HomologationMethod $co2Method,
        public readonly float $co2FullYearTariff,
        public readonly float $co2Due,
        public readonly PollutantCategory $pollutantCategory,
        public readonly float $pollutantsFullYearTariff,
        public readonly float $pollutantsDue,
        public readonly float $totalDue,
        public readonly array $exemptionReasons,
    ) {}

    /**
     * Représentation camelCase pour Inertia.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'daysAssigned' => $this->daysAssigned,
            'cumulativeDaysForPair' => $this->cumulativeDaysForPair,
            'daysInYear' => $this->daysInYear,
            'lcdExempt' => $this->lcdExempt,
            'electricExempt' => $this->electricExempt,
            'handicapExempt' => $this->handicapExempt,
            'co2Method' => $this->co2Method->value,
            'co2FullYearTariff' => $this->co2FullYearTariff,
            'co2Due' => $this->co2Due,
            'pollutantCategory' => $this->pollutantCategory->value,
            'pollutantsFullYearTariff' => $this->pollutantsFullYearTariff,
            'pollutantsDue' => $this->pollutantsDue,
            'totalDue' => $this->totalDue,
            'exemptionReasons' => $this->exemptionReasons,
        ];
    }
}
