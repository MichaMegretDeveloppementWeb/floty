<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Mini company tag rendered in the Companies column of the Drivers Index.
 */
#[TypeScript]
final class DriverListItemCompanyTagData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public string $legalName,
        public CompanyColor $color,
    ) {}
}
