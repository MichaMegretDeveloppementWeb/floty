<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-year activity detail for a company: monthly heatmap (12 entries) plus
 * the top three vehicles assigned over the year.
 */
#[TypeScript]
final class CompanyActivityYearData extends Data
{
    /**
     * @param  list<int>  $daysByMonth  Exactly 12 entries, Jan to Dec.
     * @param  list<CompanyTopVehicleData>  $topVehicles  Up to 3, sorted desc by daysUsed.
     */
    public function __construct(
        public int $year,
        public array $daysByMonth,
        #[DataCollectionOf(CompanyTopVehicleData::class)]
        public array $topVehicles,
    ) {}
}
