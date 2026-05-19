<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\User\Billing\PendingInvoiceYearData;
use Carbon\CarbonImmutable;

/**
 * Resolver of the years where invoices remain to be generated for a
 * company. Mirrors
 * {@see App\Services\Fiscal\Declaration\PendingDeclarationsResolver}
 * on the billing side · feeds the "À faire" card of the company
 * overview tab. Granularity is one entry per year (the per-month
 * detail stays on the Billing tab).
 *
 * --------------------------------------------------------------------
 * Live computation, not materialised.
 * --------------------------------------------------------------------
 *
 * Each call recomputes the "monthly invoices to generate" aggregate
 * from `contracts`, `vehicle_yearly_pricings`, `invoices`. For every
 * year covered by at least one company contract · 12 monthly calls to
 * {@see BillingBreakdownService::byCompanyForYear()}.
 *
 * Empirical cost V1 · ~50-150 ms for 1-5 companies × 1-3 years on
 * local Herd. Acceptable for the current usage (overview card with
 * low visit frequency). The internal fiscal pipeline already memoizes
 * its sub-results per request.
 *
 * Materialisation triggers (for a future V2/V3 if needed) ·
 *   - Perceived latency > 500 ms on the company Show.
 *   - More than 50 active companies with contracts spanning > 3 years.
 *   - More than 10 concurrent calls observed in logs.
 *
 * Materialisation pattern when the day comes ·
 *   1. Create `pending_invoices_cache(company_id, year, count,
 *      computed_at, payload_json)` with composite PK.
 *   2. Observers on `Contract`, `VehicleYearlyPricing`, `Invoice`
 *      invalidate the impacted `(company_id, year)` rows.
 *   3. Replace the live computation with a cache lookup; fall back to
 *      live on miss and persist the result.
 *   4. Tests · Observer invalidation + cache hit/miss + equivalence
 *      against the previous algorithm on ≥ 3 representative datasets.
 */
final readonly class PendingInvoicesResolver
{
    public function __construct(
        private BillingBreakdownService $breakdown,
        private ContractReadRepositoryInterface $contracts,
    ) {}

    /**
     * @return list<PendingInvoiceYearData>
     */
    public function pendingForCompany(int $companyId): array
    {
        $contractYears = $this->yearsCoveredByContractsForCompany($companyId);
        if ($contractYears === []) {
            return [];
        }

        $pending = [];
        foreach ($contractYears as $year) {
            $months = $this->pendingMonthsForCompanyYear($companyId, $year);

            if ($months !== []) {
                $pending[] = new PendingInvoiceYearData(
                    fiscalYear: $year,
                    missingInvoicesCount: count($months),
                );
            }
        }

        usort(
            $pending,
            static fn (PendingInvoiceYearData $a, PendingInvoiceYearData $b): int => $a->fiscalYear <=> $b->fiscalYear,
        );

        return $pending;
    }

    /**
     * Ordered list of civil months (1-12) for which an annex must be
     * generated on `(companyId, year)`. Same criteria as
     * {@see pendingForCompany()} applied to a single exercise. The
     * factored logic guarantees the UI count matches what the bulk
     * action processes.
     *
     * @return list<int> months sorted Jan → Dec
     */
    public function pendingMonthsForCompanyYear(int $companyId, int $year): array
    {
        $breakdown = $this->breakdown->byCompanyForYear($companyId, $year);

        $now = CarbonImmutable::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        $months = [];
        foreach ($breakdown->entries as $entry) {
            // Eligibility criteria ·
            //   - effective usage (`daysUsed > 0`)
            //   - pricing present (otherwise the user must fix the
            //     vehicle pricing first)
            //   - not yet invoiced (no active Invoice row)
            //   - month already elapsed (the current or future month
            //     is not billed until it is closed)
            if ($entry->daysUsed <= 0) {
                continue;
            }
            if ($entry->hasMissingPricing) {
                continue;
            }
            if ($entry->existingInvoiceId !== null) {
                continue;
            }

            $isPastMonth = $year < $currentYear
                || ($year === $currentYear && $entry->month < $currentMonth);
            if (! $isPastMonth) {
                continue;
            }

            $months[] = $entry->month;
        }

        sort($months);

        return $months;
    }

    /**
     * Same year range as the `PendingDeclarationsResolver`. Delegates
     * to `ContractReadRepository` (ADR-0013 R3 · no direct SQL in
     * services). The Eloquent `SoftDeletes` scope is applied
     * automatically.
     *
     * @return list<int>
     */
    private function yearsCoveredByContractsForCompany(int $companyId): array
    {
        return $this->contracts->findActiveYearsForCompany($companyId);
    }
}
