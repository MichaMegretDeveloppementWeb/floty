<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a monthly invoice: a single vehicle × civil month × user
 * company.
 *
 * Composed by {@see App\Services\Billing\BillingCalculator} downstream of
 * {@see App\Services\Billing\OptimalRateBreakdown}. The
 * `monthsBilled` / `weeksBilled` / `daysBilled` counters reflect the
 * chosen tariff decomposition (cheapest combo for the client) and NOT
 * the civil split, hence the coexistence with `daysUsed` which holds the
 * raw business value (days actually used in the month).
 *
 * Commercial discount semantics:
 *   - `totalCents` is the NET (gross minus discount). Existing consumers
 *     keep displaying the final amount as before.
 *   - `grossTotalCents` is the GROSS before discount (useful for the
 *     detailed billing UI). Without a discount, `grossTotalCents ==
 *     totalCents`.
 *   - `discountCents` = `grossTotalCents - totalCents`.
 *   - `appliedDiscountId` references the applied `RentalDiscount` (null
 *     when none). Enables drill-down from Invoice Show to the discount
 *     detail.
 *   - `usedDates` exposes the per-day list so `DiscountApplier` can
 *     compute partial prorata day by day. Kept after application for
 *     audit traceability (negligible payload: ~30 strings per row for a
 *     full month).
 */
#[TypeScript]
final class BillingLineData extends Data
{
    /**
     * @param  list<string>  $usedDates  ISO Y-m-d dates where this vehicle is
     *                                   actually used for this company in the
     *                                   civil month (derived from
     *                                   `expandContractsByKey` clipping). Empty
     *                                   for pre-discount rows or `InvoiceLine`
     *                                   snapshots.
     */
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        /**
         * Effective usage days (intersection of contracts ∩ civil month),
         * deduplicated: if the same vehicle has two contracts for the
         * same company in the month, shared days are counted once.
         */
        public int $daysUsed,
        public int $monthsBilled,
        public int $weeksBilled,
        public int $daysBilled,
        public int $dailyRateCents,
        public int $weeklyRateCents,
        public int $monthlyRateCents,
        /**
         * NET line total = `grossTotalCents - discountCents`. What the
         * company actually pays for this line.
         */
        public int $totalCents,
        /**
         * GROSS line total = `monthsBilled × monthlyRateCents +
         * weeksBilled × weeklyRateCents + daysBilled × dailyRateCents`.
         * Equal to `totalCents` when no discount applies.
         */
        public int $grossTotalCents = 0,
        /**
         * Commercial discount applied to this line (cents). 0 when no
         * active discount.
         */
        public int $discountCents = 0,
        /** Reference to the applied `RentalDiscount` (null when none). */
        public ?int $appliedDiscountId = null,
        /** Applied basis points snapshot (UI). Null when no discount. */
        public ?int $appliedDiscountBasisPoints = null,
        /** Discount label snapshot (tooltip without a 2nd lookup). */
        public ?string $appliedDiscountLabel = null,
        public array $usedDates = [],
    ) {}
}
