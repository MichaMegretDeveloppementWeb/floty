<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-year statistics for a company. Used both as `byYear` (single selected year)
 * and `history[]` (one entry per year with ≥ 1 contract).
 */
#[TypeScript]
final class CompanyYearStatsData extends Data
{
    public function __construct(
        public int $year,
        /** Contract days used on the year (sum across vehicle-company pairs). */
        public int $daysUsed,
        /** Active contracts count on the year. */
        public int $contractsCount,
        /** Sub-count of LCD contracts (≤ 30 days or full calendar month). */
        public int $lcdCount,
        /** Sub-count of LLD contracts (> 30 days outside a full calendar month). */
        public int $lldCount,
        /** Annual tax due for this company on the year (€, rounded to 2 decimals). */
        public float $annualTaxDue,
        /** Annual billed rent; null until billing V1.2 ships. */
        public ?float $rent,
    ) {}
}
