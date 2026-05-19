<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Contract stats for the Contracts tab on Company Show. `totalDays` is
 * intersected with the active period filter, so a Q3 filter on a full-year
 * contract counts only the 92 days of July–September.
 */
#[TypeScript]
final class CompanyContractsStatsData extends Data
{
    public function __construct(
        public int $totalDays,
        public int $lcdCount,
        public int $lldCount,
    ) {}
}
