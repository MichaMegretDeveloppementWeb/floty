<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Company option for selectors and `CompanyTag` consumers.
 */
#[TypeScript]
final class CompanyOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $shortCode,
        public string $legalName,
        public CompanyColor $color,
    ) {}
}
