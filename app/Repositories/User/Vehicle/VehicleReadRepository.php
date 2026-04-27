<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Implémentation Eloquent des lectures Vehicle.
 *
 * Eager-loading systématique des `fiscalCharacteristics` actives
 * (`effective_to IS NULL`) pour éviter tout N+1 dans les agrégations
 * fiscales en aval.
 */
final class VehicleReadRepository implements VehicleReadRepositoryInterface
{
    public function findAllForFleetView(): Collection
    {
        return Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->orderByDesc('acquisition_date')
            ->get();
    }

    public function findAllForOptions(): Collection
    {
        return Vehicle::query()
            ->whereNull('exit_date')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'model']);
    }

    public function findByIdsIndexed(array $ids): Collection
    {
        return Vehicle::query()
            ->whereIn('id', $ids)
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->get()
            ->keyBy('id');
    }

    public function findOrFailWithFiscal(int $id): Vehicle
    {
        return Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->findOrFail($id);
    }

    public function findAllForHeatmap(): Collection
    {
        return Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->orderBy('license_plate')
            ->get();
    }

    public function countActive(): int
    {
        return Vehicle::query()->whereNull('exit_date')->count();
    }
}
