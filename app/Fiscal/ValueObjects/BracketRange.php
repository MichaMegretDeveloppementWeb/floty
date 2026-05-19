<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Exceptions\Fiscal\FiscalCalculationException;

/**
 * One slice of a marginal-rate progressive bracket.
 *
 * Semantics: for the portion of the input value falling in
 * `(lowerExclusive, upperInclusive]`, the `marginalRate` applies. The
 * last slice of a bracket may have `upperInclusive = null` to express
 * an open upper bound (instead of the legacy PHP_INT_MAX).
 *
 * Immutable and validated at construction; building an inconsistent
 * slice throws immediately.
 */
final readonly class BracketRange
{
    public function __construct(
        public int $lowerExclusive,
        public ?int $upperInclusive,
        public float $marginalRate,
    ) {
        if ($upperInclusive !== null && $upperInclusive <= $lowerExclusive) {
            throw FiscalCalculationException::invalidBracket($lowerExclusive, $upperInclusive);
        }
        if ($marginalRate < 0.0) {
            throw FiscalCalculationException::negativeBracketRate($marginalRate);
        }
    }

    /**
     * Integer portion of `$value` that falls in this slice, or 0 if
     * the value is below `lowerExclusive`.
     */
    public function slice(int $value): int
    {
        if ($value <= $this->lowerExclusive) {
            return 0;
        }

        $cap = $this->upperInclusive ?? $value;

        return min($value, $cap) - $this->lowerExclusive;
    }

    /**
     * True if the slice has no upper bound (used to close a progressive
     * bracket).
     */
    public function isOpenEnded(): bool
    {
        return $this->upperInclusive === null;
    }
}
