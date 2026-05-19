<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the Drivers section on the Company Show page. Limited to the
 * membership with this company (unlike the global `DriverListItemData`).
 */
#[TypeScript]
final class CompanyDriverRowData extends Data
{
    public function __construct(
        public int $driverId,
        public int $pivotId,
        public string $fullName,
        public string $initials,
        public string $joinedAt,
        public ?string $leftAt,
        public bool $isCurrentlyActive,
        public int $contractsCount,
    ) {}
}
