<?php

declare(strict_types=1);

namespace App\DTO\Fiscal;

use App\Data\User\Fiscal\FiscalBreakdownData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Services\Fiscal\FiscalCalculator;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Résultat détaillé d'un calcul fiscal pour un couple
 * (véhicule, entreprise utilisatrice) sur un nombre de jours donné.
 *
 * DTO interne — produit par {@see FiscalCalculator}
 * et consommé par les services métier (notamment
 * {@see FleetFiscalAggregator}).
 *
 * Pour exposition au front : convertir via
 * {@see FiscalBreakdownData::fromBreakdown()}.
 *
 * Les montants sont en euros, deux décimales (arrondi commercial half-up).
 */
final readonly class FiscalBreakdown
{
    /**
     * @param  list<AppliedExemption>  $appliedExemptions  Exonérations
     *                                                     appliquées (couples raison + ruleCode)
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
