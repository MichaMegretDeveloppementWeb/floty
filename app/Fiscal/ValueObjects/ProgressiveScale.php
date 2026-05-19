<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Exceptions\Fiscal\FiscalCalculationException;

/**
 * Marginal-rate progressive bracket. Composition of continuous
 * {@see BracketRange} slices:
 *
 *   - each `lowerExclusive` must equal the previous slice's
 *     `upperInclusive` (perfect continuity, no gap or overlap)
 *   - the first slice starts at `lowerExclusive = 0`
 *   - the last slice may be open (`upperInclusive = null`) to cover
 *     infinity
 *
 * Validated at construction; any inconsistency throws a
 * {@see FiscalCalculationException} immediately.
 */
final readonly class ProgressiveScale
{
    /**
     * @param  list<BracketRange>  $brackets
     */
    public function __construct(public array $brackets)
    {
        if ($brackets === []) {
            throw FiscalCalculationException::emptyScale();
        }

        $expectedLower = 0;
        $count = count($brackets);
        foreach ($brackets as $index => $bracket) {
            if ($bracket->lowerExclusive !== $expectedLower) {
                throw FiscalCalculationException::scaleDiscontinuity(
                    $index,
                    $expectedLower,
                    $bracket->lowerExclusive,
                );
            }

            $isLast = $index === $count - 1;
            if (! $isLast && $bracket->isOpenEnded()) {
                throw FiscalCalculationException::scaleOpenBracketNotLast($index);
            }

            $expectedLower = $bracket->upperInclusive ?? PHP_INT_MAX;
        }
    }

    /**
     * Applies the bracket to an integer input (CO₂ g/km, administrative
     * horsepower, …). Returns the full-year tariff.
     */
    public function apply(int $value): float
    {
        $tariff = 0.0;
        foreach ($this->brackets as $bracket) {
            $portion = $bracket->slice($value);
            if ($portion > 0) {
                $tariff += $portion * $bracket->marginalRate;
            }
            if ($bracket->upperInclusive !== null && $value <= $bracket->upperInclusive) {
                break;
            }
        }

        return $tariff;
    }
}
