<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal\Pedagogical;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ProgressiveBracketRowData extends Data
{
    public function __construct(
        public string $label,
        public string $rate,
    ) {}
}
