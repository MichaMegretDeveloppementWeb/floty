<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Aggregate usage statistics for a vehicle on the active fiscal year.
 *
 * Feeds KPI cards, the 52-week timeline and the per-company breakdown
 * table on the vehicle Show page.
 */
#[TypeScript]
final class VehicleUsageStatsData extends Data
{
    /**
     * @param  list<VehicleCompanyUsageData>  $companies
     * @param  list<VehicleWeekUsageData>  $weeklyBreakdown
     */
    public function __construct(
        public int $fiscalYear,
        public int $daysInYear,
        public int $daysUsedThisYear,
        public float $actualTaxThisYear,
        public float $fullYearTax,
        public float $dailyTaxRate,
        #[DataCollectionOf(VehicleCompanyUsageData::class)]
        public array $companies,
        #[DataCollectionOf(VehicleWeekUsageData::class)]
        public array $weeklyBreakdown,
        public VehicleFullYearTaxBreakdownData $fullYearTaxBreakdown,
    ) {}
}
