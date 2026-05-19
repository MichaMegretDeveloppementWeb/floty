<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Monthly billing result for a (company × year × month): N vehicle lines
 * + HT total.
 *
 * Produced by {@see App\Services\Billing\BillingCalculator::calculate}.
 * If at least one vehicle has no tariff defined, the service throws
 * {@see App\Exceptions\Billing\MissingPricingException} BEFORE returning
 * this DTO; a present instance therefore guarantees tariff exhaustivity.
 *
 * Commercial discount semantics:
 *   - `totalCents` = NET (sum of `lines[].totalCents`).
 *   - `grossTotalCents` = GROSS (sum of `lines[].grossTotalCents`).
 *   - `totalDiscountCents` = sum of `lines[].discountCents`. 0 when no
 *     discount applies.
 */
#[TypeScript]
final class BillingCalculationData extends Data
{
    /**
     * @param  list<BillingLineData>  $lines  One line per vehicle, sorted by plate for display stability.
     */
    public function __construct(
        public int $companyId,
        public int $year,
        public int $month,
        #[DataCollectionOf(BillingLineData::class)]
        public array $lines,
        /** Sum of `lines[].totalCents` (= net). */
        public int $totalCents,
        /** Sum of `lines[].grossTotalCents` (= gross before discount). */
        public int $grossTotalCents = 0,
        /** Sum of `lines[].discountCents`. */
        public int $totalDiscountCents = 0,
    ) {}
}
