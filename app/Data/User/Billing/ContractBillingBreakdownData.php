<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Contract-isolated billing recap: one row per civil month the contract
 * covers.
 *
 * Calculation caveat: this recap computes cost in isolation. When the
 * vehicle has several contracts in the same month for the same company,
 * summing per-contract amounts may differ from the actual monthly invoice
 * (which consolidates days to pick the optimal multi-contract combo).
 *
 * Example:
 *   - 1 contract of 10 days in March (90/500/1800) = 1 week + 3 days = 77 000
 *   - 2 contracts of 10 days each, same month, same company = 20
 *     consolidated days = 3 weeks = 150 000
 *   - Isolated sum gives 154 000; the actual invoice gives 150 000.
 *
 * The approximation is accepted: it is useful and readable on the contract
 * page, and the actual invoice remains the source of truth.
 */
#[TypeScript]
final class ContractBillingBreakdownData extends Data
{
    /**
     * @param  list<ContractBillingMonthData>  $months  Sorted by (year, month) ascending.
     */
    public function __construct(
        #[DataCollectionOf(ContractBillingMonthData::class)]
        public array $months,
        public int $totalDaysUsed,
        public ?int $totalCents,
        public bool $hasAnyMissingPricing,
        /** GROSS cumulative over months without missing pricing. */
        public int $totalGrossCentsPartial = 0,
        /**
         * Cumulative discounts over months without missing pricing. 0
         * when no discount applies.
         */
        public int $totalDiscountCentsPartial = 0,
    ) {}
}
