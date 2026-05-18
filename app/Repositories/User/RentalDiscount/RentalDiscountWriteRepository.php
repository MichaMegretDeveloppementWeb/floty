<?php

declare(strict_types=1);

namespace App\Repositories\User\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountWriteRepositoryInterface;
use App\Models\RentalDiscount;

/**
 * Implémentation Eloquent du contrat d'écriture des réductions
 * commerciales.
 *
 * Repository sans état · singleton via
 * {@see App\Providers\RepositoryServiceProvider}.
 *
 * Toutes les mutations passent par les Actions du domaine (lot 4) qui
 * orchestrent la validation chevauchement et la transaction.
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
        // sync() supprime les associations absentes et insère les
        // nouvelles · idempotent, parfait pour update form qui passe
        // toujours la liste complète.
        $discount->vehicles()->sync($vehicleIds);
    }
}
