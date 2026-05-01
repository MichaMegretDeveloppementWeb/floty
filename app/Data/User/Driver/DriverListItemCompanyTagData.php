<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Mini-tag entreprise dans la colonne "Entreprises" de l'Index drivers.
 */
#[TypeScript]
final class DriverListItemCompanyTagData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public CompanyColor $color,
    ) {}
}
