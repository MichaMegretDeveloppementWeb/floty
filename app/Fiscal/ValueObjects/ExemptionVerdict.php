<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Résultat d'évaluation d'une `ExemptionRule` sur un contexte.
 *
 * Modes d'exonération :
 *
 * - `notExempt()`            : la règle ne s'applique pas
 * - `full(...)`              : exonération totale (deux taxes) — les
 *                              tarifs annuels pleins restent affichés
 *                              dans le breakdown
 * - `fullZeroingTariffs(...)`: exonération totale ET les tarifs annuels
 *                              sont mis à zéro dans le breakdown (cas
 *                              handicap, où l'on ne veut pas montrer
 *                              « ce que vous auriez payé »)
 * - `onlyCo2(...)`           : exonération CO₂ seule, polluants normal
 *                              (cas électrique / hydrogène)
 * - `onlyPollutants(...)`    : exonération polluants seule, CO₂ normal
 * - `partialDays(count, ...)`: exonération journalière — `count` jours
 *                              sont retirés du numérateur du prorata
 *                              R-2024-002. Les tarifs annuels restent
 *                              visibles. Utilisé pour LCD per-contract
 *                              (R-2024-021) et indispos fiscalement
 *                              réductrices (R-2024-008).
 *
 * Le `reason` est un message français destiné au breakdown utilisateur
 * (PDF, drawer planning, etc.).
 */
final readonly class ExemptionVerdict
{
    private function __construct(
        public bool $isExempt,
        public ?ExemptionScope $scope,
        public ?string $reason,
        public bool $zeroesFullYearTariffs,
        public ?int $exemptDaysCount = null,
        public ?string $ruleCode = null,
    ) {}

    public static function notExempt(): self
    {
        return new self(false, null, null, false);
    }

    public static function full(string $reason, string $ruleCode): self
    {
        return new self(true, ExemptionScope::Both, $reason, false, null, $ruleCode);
    }

    public static function fullZeroingTariffs(string $reason, string $ruleCode): self
    {
        return new self(true, ExemptionScope::Both, $reason, true, null, $ruleCode);
    }

    public static function onlyCo2(string $reason, string $ruleCode): self
    {
        return new self(true, ExemptionScope::Co2Only, $reason, false, null, $ruleCode);
    }

    public static function onlyPollutants(string $reason, string $ruleCode): self
    {
        return new self(true, ExemptionScope::PollutantsOnly, $reason, false, null, $ruleCode);
    }

    /**
     * Exonération journalière : `daysCount` jours sont retirés du
     * numérateur du prorata. Les tarifs annuels pleins restent visibles
     * dans le breakdown (info utilisateur).
     */
    public static function partialDays(int $daysCount, string $reason, string $ruleCode): self
    {
        return new self(true, null, $reason, false, $daysCount, $ruleCode);
    }
}
