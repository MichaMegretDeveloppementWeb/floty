<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Barème forfaitaire d'une règle de tarification · ex. tarif
 * polluants (catégorie → montant forfaitaire annuel · Phase 13 D5.12).
 *
 * `header` · 2 colonnes (libellé de catégorie, montant). Ex.
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
