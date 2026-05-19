<?php

declare(strict_types=1);

namespace App\Fiscal\Registry;

use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_008_ReductiveUnavailability;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Overlay registry that swaps the canonical LCD rule (R-YYYY-021) with
 * an opt-out-carrying decorator when computing a specific declaration.
 *
 * Why: the canonical R-YYYY-021 rule has no knowledge of human review
 * decisions. To make a "Requalified" decision on a cluster strip the
 * LCD exemption from the cluster's contracts without changing their
 * duration, the rule is swapped at runtime by a decorator
 * (R2024_021_WithOptOuts or R2025_021_WithOptOuts). This swap must also
 * apply to **consumer rules** that depend on R-YYYY-021 via DI
 * (R-YYYY-008 reducers).
 *
 * Multi-year architecture: the registry is scoped to a fiscal year
 * (`$fiscalYear`) because a declaration covers a fixed
 * `(company, year)` pair. The decorator must match that year (checked
 * in the constructor). The canonical → decorator mapping is resolved
 * by {@see resolveRuleInstance()} switching on the year.
 *
 * Known consumers of LcdQualifier: V1 has a single pipeline consumer
 * (R-YYYY-008 ReductiveUnavailability). Any future rule depending on
 * `LcdQualifier` via DI must add its class-string + manual instantiation
 * logic in {@see resolveRuleInstance()}.
 *
 * Usage: instantiated ad-hoc in `DeclarationFiscalEngine`, paired with
 * a fresh {@see RuleEffectiveSegmenter} (the
 * global singleton segmenter is NOT reused to avoid cross-declaration
 * cache pollution).
 */
final class OverlayedRuleRegistry extends FiscalRuleRegistry
{
    /**
     * @param  ExemptionRule&LcdQualifier  $lcdDecorator  runtime
     *                                                    decorator of the canonical LCD rule for `$fiscalYear`.
     */
    public function __construct(
        private readonly Container $container,
        FiscalRuleRegistry $base,
        private readonly ExemptionRule&LcdQualifier $lcdDecorator,
        private readonly int $fiscalYear,
    ) {
        parent::__construct($container);

        if ($lcdDecorator->applicabilityStart()->year !== $fiscalYear) {
            throw new InvalidArgumentException(sprintf(
                'OverlayedRuleRegistry · le décorateur LCD année %d ne correspond pas à $fiscalYear=%d.',
                $lcdDecorator->applicabilityStart()->year,
                $fiscalYear,
            ));
        }

        // Re-publish the base registry's year → classes mapping so
        // `registeredYears()`, `classesForYear()` and
        // `rulesEffectiveAt()` stay consistent without overriding. The
        // instance substitution happens at read time in `rulesForYear()`.
        foreach ($base->registeredYears() as $year) {
            $this->register($year, $base->classesForYear($year));
        }
    }

    /**
     * @return list<FiscalRule>
     */
    public function rulesForYear(int $year): array
    {
        $rules = [];
        foreach ($this->classesForYear($year) as $class) {
            $rules[] = $this->resolveRuleInstance($class);
        }

        return $rules;
    }

    /**
     * @param  class-string<FiscalRule>  $class
     */
    private function resolveRuleInstance(string $class): FiscalRule
    {
        // Substitution scoped to the registry's fiscal year. Classes of
        // other years go through the standard container resolution.
        if ($this->fiscalYear === 2024) {
            // Direct substitution: R-2024-021 → decorator.
            if ($class === R2024_021_ShortTermRental::class) {
                return $this->lcdDecorator;
            }

            // Indirect substitution: R-2024-008 depends on LcdQualifier
            // via constructor. Instantiate it manually, injecting the
            // decorator so its LCD qualification reflects the opt-outs.
            if ($class === R2024_008_ReductiveUnavailability::class) {
                return new R2024_008_ReductiveUnavailability($this->lcdDecorator);
            }
        }

        if ($this->fiscalYear === 2025) {
            if ($class === R2025_021_ShortTermRental::class) {
                return $this->lcdDecorator;
            }

            if ($class === R2025_008_ReductiveUnavailability::class) {
                return new R2025_008_ReductiveUnavailability($this->lcdDecorator);
            }
        }

        return $this->container->make($class);
    }
}
