<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Data\User\Invoice\InvoiceDivergenceData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Invoice;
use App\Services\Billing\BillingCalculator;

/**
 * Compares an emitted invoice (frozen snapshot) against the current
 * contractual reality (live recompute) to surface divergence (ADR-0008).
 *
 * One call equals one full `BillingCalculator::calculate` for the
 * `(company × year × month)` tuple. Acceptable on detail screens; on
 * paginated indexes the caller should batch.
 */
final readonly class InvoiceDivergenceChecker
{
    public function __construct(
        private BillingCalculator $calculator,
    ) {}

    public function check(Invoice $invoice): InvoiceDivergenceData
    {
        $invoicedDaysUsed = (int) $invoice->lines->sum('days_used');
        $invoicedTotalCents = (int) $invoice->total_ht_cents;

        try {
            $current = $this->calculator->calculate(
                companyId: $invoice->company_id,
                year: $invoice->year,
                month: $invoice->month,
            );

            $currentDaysUsed = (int) array_sum(array_map(
                static fn ($line) => $line->daysUsed,
                $current->lines,
            ));

            $hasDivergence = $currentDaysUsed !== $invoicedDaysUsed
                || $current->totalCents !== $invoicedTotalCents;

            return new InvoiceDivergenceData(
                hasDivergence: $hasDivergence,
                invoicedDaysUsed: $invoicedDaysUsed,
                invoicedTotalCents: $invoicedTotalCents,
                currentDaysUsed: $currentDaysUsed,
                currentTotalCents: $current->totalCents,
            );
        } catch (MissingPricingException) {
            // Yearly pricing has since been removed; the invoice can no
            // longer be reproduced verbatim, which counts as a strong
            // divergence.
            return new InvoiceDivergenceData(
                hasDivergence: true,
                invoicedDaysUsed: $invoicedDaysUsed,
                invoicedTotalCents: $invoicedTotalCents,
                currentDaysUsed: null,
                currentTotalCents: null,
            );
        }
    }
}
