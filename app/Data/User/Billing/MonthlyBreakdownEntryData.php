<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One cell of the 12-month × {days, amount} recap table for an entity
 * (vehicle or company) over a given year.
 *
 * `totalCents` is `null` when at least one vehicle present in the month
 * has no yearly tariff: the amount cannot be estimated reliably, so it
 * is flagged "billable but blocked" via `hasMissingPricing = true`.
 *
 * `daysUsed` is always defined: pure aggregation of business data, no
 * tariff dependency.
 *
 * `existingInvoiceId` / `existingInvoiceNumber` are populated on the
 * Company recap when an invoice already exists for the
 * (company, year, month) tuple. The UI then swaps the "Générer" button
 * for a "Voir #YYYY-MM-NNNN" link. Always `null` on the vehicle recap
 * (several companies may issue invoices in the same month).
 *
 * `invoicedDaysUsed` / `invoicedTotalCents` carry the snapshot frozen at
 * issuance (never recomputed). Comparing with `daysUsed` / `totalCents`
 * (dynamic recompute) lets the UI detect divergence and warn about
 * "data changed since issuance". Always `null` when no invoice exists.
 */
#[TypeScript]
final class MonthlyBreakdownEntryData extends Data
{
    public function __construct(
        public int $month,
        public int $daysUsed,
        public ?int $totalCents,
        public bool $hasMissingPricing,
        public ?int $existingInvoiceId = null,
        public ?string $existingInvoiceNumber = null,
        public ?int $invoicedDaysUsed = null,
        public ?int $invoicedTotalCents = null,
        /**
         * GROSS month total (= sum `lines[].grossTotalCents` of the
         * monthly computation). `null` when a tariff is missing (mirrors
         * `totalCents`). Equal to `totalCents` when no commercial
         * discount applies.
         */
        public ?int $grossTotalCents = null,
        /**
         * Sum of commercial discounts applied during the month.
         * `null` when a tariff is missing, `0` when no discount applies.
         */
        public ?int $totalDiscountCents = null,
        /**
         * GROSS snapshot frozen at issuance. `null` until an invoice is
         * issued. Lets the UI display gross/discount/net of the invoice
         * snapshot alongside the dynamic recompute.
         */
        public ?int $invoicedGrossTotalCents = null,
        public ?int $invoicedTotalDiscountCents = null,
    ) {}
}
