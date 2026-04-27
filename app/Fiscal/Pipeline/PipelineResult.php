<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;

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
 * + ADR-0009 — pas de version, juste le rule_code).
 *
 * @phpstan-type FiscalRuleCode string
 */
final readonly class PipelineResult
{
    /**
     * @param  list<string>  $exemptionReasons
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
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public float $pollutantsDue,
        public float $totalDue,
        public array $exemptionReasons,
        public array $appliedRuleCodes,
    ) {}
}
