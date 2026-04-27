<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Résultat d'évaluation d'une `ExemptionRule` sur un contexte.
 *
 * - `notExempt()`            : la règle ne s'applique pas
 * - `full(...)`              : exonération totale (deux taxes) — les
 *                              tarifs annuels pleins restent affichés
 *                              dans le breakdown (cas LCD)
 * - `fullZeroingTariffs(...)`: exonération totale ET les tarifs annuels
 *                              sont mis à zéro dans le breakdown (cas
 *                              handicap, où l'on ne veut pas montrer
 *                              « ce que vous auriez payé »)
 * - `onlyCo2(...)`           : exonération CO₂ seule, polluants normal
 *                              (cas électrique / hydrogène)
 * - `onlyPollutants(...)`    : exonération polluants seule, CO₂ normal
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
    ) {}

    public static function notExempt(): self
    {
        return new self(false, null, null, false);
    }

    public static function full(string $reason): self
    {
        return new self(true, ExemptionScope::Both, $reason, false);
    }

    public static function fullZeroingTariffs(string $reason): self
    {
        return new self(true, ExemptionScope::Both, $reason, true);
    }

    public static function onlyCo2(string $reason): self
    {
        return new self(true, ExemptionScope::Co2Only, $reason, false);
    }

    public static function onlyPollutants(string $reason): self
    {
        return new self(true, ExemptionScope::PollutantsOnly, $reason, false);
    }
}
