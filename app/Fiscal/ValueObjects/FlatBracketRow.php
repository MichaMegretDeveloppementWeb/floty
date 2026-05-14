<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Une ligne d'un {@see FlatBracketsTable}. La `note` est un astérisque
 * d'explication facultatif affiché sous le tableau (ex. norme Euro de
 * référence pour une catégorie).
 */
final readonly class FlatBracketRow
{
    public function __construct(
        public string $category,
        public string $amount,
        public ?string $note = null,
    ) {}
}
