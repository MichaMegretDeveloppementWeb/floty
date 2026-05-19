<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-civil-month annual recap for a vehicle or company.
 *
 * Always 12 entries (months 1 → 12), even for unused months, so the grid
 * layout stays stable.
 *
 * `yearTotalCents` is `null` when at least one month has a missing tariff
 * (`hasMissingPricing = true`): the exact cumulative cannot be computed.
 *
 * `yearTotalCentsPartial` is always populated: sum of months without a
 * missing tariff. Equals `yearTotalCents` when `hasAnyMissingPricing` is
 * `false`; otherwise gives an honest view of the priced portion.
 */
#[TypeScript]
final class MonthlyBillingBreakdownData extends Data
{
    /**
     * @param  list<MonthlyBreakdownEntryData>  $entries  Always length 12 (one entry per civil month, January first).
     */
    public function __construct(
        public int $year,
        #[DataCollectionOf(MonthlyBreakdownEntryData::class)]
        public array $entries,
        public int $yearTotalDaysUsed,
        public ?int $yearTotalCents,
        public int $yearTotalCentsPartial,
        public bool $hasAnyMissingPricing,
        /**
         * Annual GROSS total (sum of `entries[].grossTotalCents` for
         * months without missing pricing). Equal to
         * `yearTotalCentsPartial` when no commercial discount applies.
         */
        public int $yearTotalGrossCentsPartial = 0,
        /**
         * Annual sum of commercial discounts applied (over months without
         * missing pricing). 0 when no discount applies.
         */
        public int $yearTotalDiscountCentsPartial = 0,
    ) {}
}
