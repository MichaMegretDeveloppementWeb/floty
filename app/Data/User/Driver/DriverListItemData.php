<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne du tableau Index drivers (Phase 06 V1.2).
 *
 * `activeCompanies` ne contient que les memberships actives (left_at NULL).
 * `totalActiveCompaniesCount` permet l'affichage condensé "+N" si plus de 2.
 */
#[TypeScript]
final class DriverListItemData extends Data
{
    /**
     * @param  array<int, DriverListItemCompanyTagData>  $activeCompanies
     */
    public function __construct(
        public int $id,
        public string $fullName,
        public string $initials,
        public array $activeCompanies,
        public int $totalActiveCompaniesCount,
        public int $contractsCount,
    ) {}
}
