<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * One row of a {@see FlatBracketsTable}. `note` is an optional
 * asterisk-style explanation shown below the table (e.g. reference
 * Euro norm for a category).
 */
final readonly class FlatBracketRow
{
    public function __construct(
        public string $category,
        public string $amount,
        public ?string $note = null,
    ) {}
}
