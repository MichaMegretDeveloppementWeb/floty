<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Une tranche d'un {@see ProgressiveBracketsTable}. Le label décrit
 * la plage (ex. `'0 à 14 g/km'`) et le rate le tarif marginal
 * appliqué (ex. `'0 €'`, `'1 € / g'`).
 */
final readonly class ProgressiveBracketRow
{
    public function __construct(
        public string $label,
        public string $rate,
    ) {}
}
