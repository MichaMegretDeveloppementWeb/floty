<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts\Concerns;

/**
 * Provides the default `isActive() = true` behaviour for fiscal rules.
 *
 * Kept separate from {@see AnnualRuleTrait} so the orthogonal concerns
 * (temporal scope vs active flag) do not couple: a partial rule can be
 * active and an annual rule can be inactive (R-2024-018 OIG,
 * R-2024-019 IndividualBusiness in V1).
 *
 * To disable a rule, do NOT use this trait and implement directly
 * `isActive(): bool { return false; }`.
 */
trait RuleActiveByDefaultTrait
{
    public function isActive(): bool
    {
        return true;
    }
}
