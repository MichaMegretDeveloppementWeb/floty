<?php

declare(strict_types=1);

namespace App\Services\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Exceptions\RentalDiscount\RentalDiscountOverlapException;
use App\Models\RentalDiscount;

/**
 * Application-level guard against overlap between commercial
 * discounts of a single company. Called by the
 * `CreateRentalDiscountAction` and `UpdateRentalDiscountAction`, and
 * exposed via the AJAX endpoint `/rental-discounts/check-conflicts`
 * for live UI feedback.
 *
 * Business rule · two discounts conflict iff their `[start, end]`
 * periods overlap (inclusive) AND the intersection of their vehicle
 * sets is non-empty (with the « empty pivot = every vehicle »
 * semantics). Not enforced through a MySQL trigger because rule 2
 * involves pivot-table intersection with the « empty = all » semantic
 * that a CHECK cannot express. Application validation + feature tests
 * cover the five cases (disjoint, period overlap with disjoint
 * vehicles, non-empty intersection, all + subset, all + all).
 */
final readonly class RentalDiscountConflictService
{
    public function __construct(
        private RentalDiscountReadRepositoryInterface $reader,
    ) {}

    /**
     * Returns the discounts of the same company conflicting with the
     * candidate (period × vehicles). Empty list = no conflict.
     *
     * `$vehicleIds` ·
     *   - empty list = « the candidate targets every vehicle » →
     *     conflicts with any discount overlapping in period.
     *   - non-empty = candidate on a subset → conflict iff the
     *     existing discount also targets every vehicle OR the
     *     subset intersection is non-empty.
     *
     * `$excludeId` removes a discount currently being edited.
     *
     * @param  list<int>  $vehicleIds
     * @return list<RentalDiscount>
     */
    public function findOverlapping(
        int $companyId,
        string $startDate,
        string $endDate,
        array $vehicleIds,
        ?int $excludeId = null,
    ): array {
        $candidates = $this->reader->findOverlappingForCompany(
            $companyId,
            $startDate,
            $endDate,
            $excludeId,
        );

        $candidateAppliesToAll = $vehicleIds === [];
        $candidateVehicleSet = array_flip($vehicleIds);

        $conflicts = [];
        foreach ($candidates as $existing) {
            $existingVehicleIds = $existing->vehicles->pluck('id')->all();
            $existingAppliesToAll = $existingVehicleIds === [];

            // When either side targets « all », the intersection is
            // necessarily non-empty as long as at least one vehicle
            // exists on the company (in practice there always is one
            // over the period · otherwise the user would have no
            // reason to create the discount).
            if ($candidateAppliesToAll || $existingAppliesToAll) {
                $conflicts[] = $existing;

                continue;
            }

            foreach ($existingVehicleIds as $vehicleId) {
                if (isset($candidateVehicleSet[$vehicleId])) {
                    $conflicts[] = $existing;
                    break;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Throwing variant for backend Actions (Create/Update).
     *
     * @param  list<int>  $vehicleIds
     *
     * @throws RentalDiscountOverlapException
     */
    public function assertNoConflict(
        int $companyId,
        string $startDate,
        string $endDate,
        array $vehicleIds,
        ?int $excludeId = null,
    ): void {
        $conflicts = $this->findOverlapping(
            $companyId,
            $startDate,
            $endDate,
            $vehicleIds,
            $excludeId,
        );

        if ($conflicts !== []) {
            throw RentalDiscountOverlapException::forDiscounts($conflicts);
        }
    }
}
