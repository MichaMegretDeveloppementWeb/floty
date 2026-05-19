<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts\Concerns;

use Carbon\CarbonImmutable;

/**
 * Adopted by any fiscal rule whose applicability covers a full fiscal
 * year (the default case for the vast majority of rules).
 *
 * The consumer class must provide {@see fiscalYear()}; temporal bounds
 * are then derived automatically:
 *   - `applicabilityStart()` returns `{year}-01-01 00:00:00`
 *   - `applicabilityEnd()` returns `{year}-12-31 23:59:59`
 *
 * For a partial rule (appearing or disappearing mid-year), do NOT use
 * this trait: implement `applicabilityStart()` / `applicabilityEnd()`
 * directly.
 *
 * The active-flag default lives in {@see RuleActiveByDefaultTrait}.
 */
trait AnnualRuleTrait
{
    abstract public function fiscalYear(): int;

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create($this->fiscalYear(), 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create($this->fiscalYear(), 12, 31, 23, 59, 59);
    }
}
