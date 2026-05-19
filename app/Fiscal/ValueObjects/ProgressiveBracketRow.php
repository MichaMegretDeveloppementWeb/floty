<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * One row of a {@see ProgressiveBracketsTable}. `label` describes the
 * range (e.g. `'0 à 14 g/km'`); `rate` is the applied marginal rate
 * (e.g. `'0 €'`, `'1 € / g'`).
 */
final readonly class ProgressiveBracketRow
{
    public function __construct(
        public string $label,
        public string $rate,
    ) {}
}
