<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Sortie structurée d'un calcul fiscal complet (cf. ADR-0006 § 2 étape 8).
 *
 * Ce DTO interne sert à la fois :
 *   - de retour public du {@see FiscalPipeline::execute()},
 *   - de pivot pour la conversion vers le DTO de présentation
 *     `FiscalBreakdown` (compat consommateurs existants).
 *
 * Les `appliedRuleCodes` permettent au snapshot PDF de référencer
 * précisément quelles règles ont participé au calcul (ADR-0006 § 5
 * + ADR-0009 - pas de version, juste le rule_code).
 *
 * @phpstan-type FiscalRuleCode string
 */
final readonly class PipelineResult
{
    /**
     * `co2Due`, `pollutantsDue`, `totalDue` sont **arrondis half-up à
     * 2 décimales** pour l'affichage par couple (PDF ligne véhicule,
     * drawer planning). Les `*Raw` portent la valeur **avant arrondi**
     * - utilisés par le {@see FleetFiscalAggregator}
     * pour appliquer R-2024-003 (un seul arrondi par redevable au
     * niveau entreprise).
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
