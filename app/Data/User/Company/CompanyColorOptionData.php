<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Company color option for the Create/Edit form selector.
 */
#[TypeScript]
final class CompanyColorOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
