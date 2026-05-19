<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response of `POST /app/planning/preview-rentals`: standalone rent of a
 * single contract, consistent with the final monthly invoice.
 *
 * The service applies the same logic as monthly invoicing: per-month
 * split, `OptimalRateBreakdown` per month, then active discounts via
 * `DiscountApplier`. `grossTotalCents`, `discountCents` and `netTotalCents`
 * are consistent (`net = gross - discount`).
 *
 * `hasMissingPricing = true` when at least one covered month has no
 * annual rate for the vehicle. Totals are then `null` (preview impossible)
 * and the UI prompts the user to complete pricing.
 *
 * `appliedDiscountLabel` / `appliedDiscountBasisPoints` expose the
 * DOMINANT discount applied over the range (uniqueness guaranteed by
 * `RentalDiscountConflictService`); null when none matched the dates.
 */
#[TypeScript]
final class RentalPreviewData extends Data
{
    /**
     * @param  list<RentalMonthlyImpactData>  $monthlyImpact  Per calendar month touched by the synthetic contract; empty when `hasMissingPricing`.
     */
    public function __construct(
        public int $daysCount,
        public ?int $grossTotalCents,
        public ?int $discountCents,
        public ?int $netTotalCents,
        public ?string $appliedDiscountLabel,
        public ?int $appliedDiscountBasisPoints,
        public bool $hasMissingPricing,
        #[DataCollectionOf(RentalMonthlyImpactData::class)]
        public array $monthlyImpact = [],
    ) {}
}
