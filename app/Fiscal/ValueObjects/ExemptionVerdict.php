<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Verdict produced by an `ExemptionRule` for a given context.
 *
 * Exemption modes:
 * - `notExempt()`            · the rule does not apply
 * - `full(...)`              · total exemption on both taxes; full-year
 *                              tariffs remain visible in the breakdown
 * - `fullZeroingTariffs(...)`· total exemption AND zeroes the full-year
 *                              tariffs in the breakdown (handicap case
 *                              where we hide the "what you would have
 *                              paid" amount)
 * - `onlyCo2(...)`           · CO₂ exemption only (electric / hydrogen)
 * - `onlyPollutants(...)`    · pollutants exemption only
 * - `partialDays(count, ...)`· daily exemption: `count` days subtracted
 *                              from the R-2024-002 prorata numerator.
 *                              Full-year tariffs stay visible. Used by
 *                              per-contract LCD (R-2024-021) and
 *                              reductive unavailabilities (R-2024-008).
 *
 * `reason` is a French message destined for the user-facing breakdown
 * (PDF, planning drawer, etc.).
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
     * Daily exemption: `daysCount` days are subtracted from the prorata
     * numerator. Full-year tariffs stay visible in the breakdown.
     */
    public static function partialDays(int $daysCount, string $reason, string $ruleCode): self
    {
        return new self(true, null, $reason, false, $daysCount, $ruleCode);
    }
}
