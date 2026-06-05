<?php

declare(strict_types=1);

namespace App\Services\Billing\Discount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;

/**
 * Preloads in a single SQL the active discounts for a
 * `(company × year)` or a multi-company batch, and builds a
 * {@see ResolvedDiscountIndex} usable by the {@see DiscountApplier} as
 * O(1) lookups. Stateless service, registered as a singleton.
 */
final readonly class DiscountResolver
{
    public function __construct(
        private RentalDiscountReadRepositoryInterface $reader,
    ) {}

    /**
     * Single SQL for the `(company × year)` active discounts, with
     * `vehicles` eager-loaded.
     */
    public function preloadForCompanyYear(int $companyId, int $year): ResolvedDiscountIndex
    {
        $discounts = $this->reader->findActiveForCompanyYear($companyId, $year);

        return ResolvedDiscountIndex::fromDiscounts($discounts);
    }

    /**
     * Cross-year variant of {@see preloadForCompanyYear} for one company
     * · one SQL over the whole year range, then a per-year
     * {@see ResolvedDiscountIndex} built in memory (a discount is bucketed
     * into every year it overlaps). Collapses the per-year N+1 of the
     * company fiche pending-invoices batch.
     *
     * @param  list<int>  $years
     * @return array<int, ResolvedDiscountIndex> year → index
     */
    public function preloadForCompanyYears(int $companyId, array $years): array
    {
        if ($years === []) {
            return [];
        }

        $discounts = $this->reader->findActiveForCompanyYears($companyId, min($years), max($years));

        $result = [];
        foreach ($years as $year) {
            $yearStart = sprintf('%04d-01-01', $year);
            $yearEnd = sprintf('%04d-12-31', $year);

            $yearDiscounts = $discounts
                ->filter(static fn ($discount): bool => $discount->start_date->toDateString() <= $yearEnd
                    && $discount->end_date->toDateString() >= $yearStart)
                ->values()
                ->all();

            $result[$year] = ResolvedDiscountIndex::fromDiscounts($yearDiscounts);
        }

        return $result;
    }

    /**
     * Single SQL for the active discounts of N companies on a year.
     * Returns a per-company index (each company has its own
     * `ResolvedDiscountIndex`) so isolation is preserved. Used by
     * cross-company batches such as the Dashboard
     * `totalRecettesForYears` aggregator.
     *
     * @param  list<int>  $companyIds
     * @return array<int, ResolvedDiscountIndex> companyId → index
     */
    public function preloadForCompaniesYear(array $companyIds, int $year): array
    {
        if ($companyIds === []) {
            return [];
        }

        $all = $this->reader->findActiveForCompaniesYear($companyIds, $year);

        $byCompany = [];
        foreach ($all as $discount) {
            $byCompany[(int) $discount->company_id][] = $discount;
        }

        // Build an index even for companies without discount so
        // consumers can `isEmpty()` directly without a null check.
        $result = [];
        foreach ($companyIds as $companyId) {
            $result[$companyId] = ResolvedDiscountIndex::fromDiscounts(
                $byCompany[$companyId] ?? [],
            );
        }

        return $result;
    }
}
