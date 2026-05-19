<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the Drivers Index table.
 *
 * `activeCompanies` lists only memberships with `left_at IS NULL`;
 * `totalActiveCompaniesCount` enables a condensed "+N" overflow display.
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
