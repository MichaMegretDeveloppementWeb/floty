<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\RentalDiscount;

use App\Models\RentalDiscount;

/**
 * Writes on commercial rental discounts.
 *
 * All mutations go through the domain Actions
 * (`CreateRentalDiscountAction`, `UpdateRentalDiscountAction`,
 * `DeleteRentalDiscountAction`) which orchestrate overlap validation
 * before the transactional write.
 */
interface RentalDiscountWriteRepositoryInterface
{
    /**
     * Persists a new discount. Vehicle pivot sync is done via
     * {@see syncVehicles}.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): RentalDiscount;

    /**
     * Updates the discount's scalar attributes (without touching the
     * pivot · cf. {@see syncVehicles}).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(RentalDiscount $discount, array $attributes): RentalDiscount;

    /**
     * Soft-deletes the discount · preserves the ID for reference from
     * already-emitted invoice_lines (immutable audit).
     */
    public function softDelete(RentalDiscount $discount): void;

    /**
     * Syncs the list of vehicles targeted by the discount. Empty list
     * = empty pivot = semantic "applies to all vehicles of the company
     * over the period".
     *
     * @param  list<int>  $vehicleIds
     */
    public function syncVehicles(RentalDiscount $discount, array $vehicleIds): void;
}
