<?php

declare(strict_types=1);

namespace App\Repositories\User\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Models\RentalDiscount;

/**
 * Eloquent implementation of commercial rental discount writes.
 *
 * Stateless repository (singleton via
 * {@see App\Providers\RepositoryServiceProvider}).
 *
 * All mutations go through the domain Actions which orchestrate
 * overlap validation and the transaction.
 */
final class RentalDiscountWriteRepository implements RentalDiscountWriteRepositoryInterface
{
    public function create(array $attributes): RentalDiscount
    {
        return RentalDiscount::query()->create($attributes);
    }

    public function update(RentalDiscount $discount, array $attributes): RentalDiscount
    {
        $discount->update($attributes);

        return $discount->refresh();
    }

    public function softDelete(RentalDiscount $discount): void
    {
        $discount->delete();
    }

    public function syncVehicles(RentalDiscount $discount, array $vehicleIds): void
    {
        // sync() drops missing associations and inserts the new ones
        // · idempotent, perfect for an update form that always passes
        // the full list.
        $discount->vehicles()->sync($vehicleIds);
    }
}
