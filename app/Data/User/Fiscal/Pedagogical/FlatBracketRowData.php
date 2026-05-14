<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal\Pedagogical;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class FlatBracketRowData extends Data
{
    public function __construct(
        public string $category,
        public string $amount,
        public ?string $note = null,
    ) {}
}
