<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Marginal-rate progressive bracket of a pricing rule (e.g. WLTP CO₂,
 * NEDC, PA), formatted for display.
 *
 * Numerical values used by the engine live in the rule's `apply()`
 * method. Both representations must stay consistent (covered by the
 * bracket golden tests).
 *
 * `header`: two columns (bracket label, marginal rate), e.g.
 * `['Tranche WLTP', 'Tarif marginal']`.
 *
 * `unit`: unit of the input variable (g/km, CV, etc.).
 */
final readonly class ProgressiveBracketsTable
{
    /**
     * @param  array{0: string, 1: string}  $header
     * @param  list<ProgressiveBracketRow>  $rows
     */
    public function __construct(
        public string $unit,
        public array $header,
        public array $rows,
    ) {}
}
