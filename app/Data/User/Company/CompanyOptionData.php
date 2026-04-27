<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option entreprise pour les `<SelectInput>` (Attribution rapide,
 * Drawer planning) et la consommation par les composants `CompanyTag`.
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
