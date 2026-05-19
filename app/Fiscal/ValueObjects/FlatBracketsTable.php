<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Flat-tariff bracket of a pricing rule (e.g. pollutants: category →
 * flat annual amount).
 *
 * `header`: two columns (category label, amount), e.g.
 * `['Catégorie polluants', 'Tarif annuel']`.
 */
final readonly class FlatBracketsTable
{
    /**
     * @param  array{0: string, 1: string}  $header
     * @param  list<FlatBracketRow>  $rows
     */
    public function __construct(
        public array $header,
        public array $rows,
    ) {}
}
