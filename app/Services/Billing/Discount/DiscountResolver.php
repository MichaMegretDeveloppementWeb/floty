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
