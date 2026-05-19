<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-year statistics for a vehicle. Feeds both the current-year KPI card
 * and the historical evolution mini-table on the Show page.
 */
#[TypeScript]
final class VehicleYearStatsData extends Data
{
    public function __construct(
        public int $year,
        public int $daysUsed,
        public int $contractsCount,
        public float $actualTax,
        public float $fullYearTax,
        /**
         * Annual rental amount (sum of the 12 monthly invoices across companies)
         * in euros. Null when at least one month raises MissingPricing.
         */
        public ?float $rentalPrice = null,
    ) {}
}
