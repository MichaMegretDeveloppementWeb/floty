<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public function findAllForFleetView(bool $includeExited = false): Collection
    {
        $query = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->orderByDesc('acquisition_date');

        if (! $includeExited) {
            $query->activeAt(CarbonImmutable::today());
        }

        return $query->get();
    }

    public function existsAny(): bool
    {
        return Vehicle::query()->exists();
    }

    public function paginateForIndex(VehicleIndexQueryData $query): LengthAwarePaginator
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')]);

        if (! $query->includeExited) {
            $eloquentQuery->activeAt(CarbonImmutable::today());
        }

        if ($query->status !== null) {
            $eloquentQuery->where('current_status', $query->status->value);
        }

        // Search LIKE sur license_plate OR brand OR model.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function ($w) use ($term): void {
                $w->where('license_plate', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term);
            });
        }

        // Filtres VFC active (effective_to IS NULL).
        if ($query->energySource !== null) {
            $energyValue = $query->energySource->value;
            $eloquentQuery->whereHas('fiscalCharacteristics', function ($q) use ($energyValue): void {
                $q->whereNull('effective_to')->where('energy_source', $energyValue);
            });
        }

        if ($query->pollutantCategory !== null) {
            $pollutantValue = $query->pollutantCategory->value;
            $eloquentQuery->whereHas('fiscalCharacteristics', function ($q) use ($pollutantValue): void {
                $q->whereNull('effective_to')->where('pollutant_category', $pollutantValue);
            });
        }

        if ($query->handicapAccess === true) {
            $eloquentQuery->whereHas('fiscalCharacteristics', function ($q): void {
                $q->whereNull('effective_to')->where('handicap_access', true);
            });
        }

        // Fourchette année de 1ʳᵉ immatriculation française.
        if ($query->firstRegistrationYearMin !== null) {
            $eloquentQuery->whereYear('first_french_registration_date', '>=', $query->firstRegistrationYearMin);
        }

        if ($query->firstRegistrationYearMax !== null) {
            $eloquentQuery->whereYear('first_french_registration_date', '<=', $query->firstRegistrationYearMax);
        }

        // Tri whitelist (cf. VehicleIndexQueryData::allowedSortKeys()).
        match ($query->sortKey) {
            'licensePlate' => $eloquentQuery->orderBy('license_plate', $direction),
            'model' => $eloquentQuery
                ->orderBy('brand', $direction)
                ->orderBy('model', $direction),
            'firstFrenchRegistrationDate' => $eloquentQuery->orderBy('first_french_registration_date', $direction),
            'acquisitionDate' => $eloquentQuery->orderBy('acquisition_date', $direction),
            'currentStatus' => $eloquentQuery->orderBy('current_status', $direction),
            // Défaut : tri historique acquisition_date DESC (ordre d'achat).
            default => $eloquentQuery->orderByDesc('acquisition_date'),
        };

        return $eloquentQuery->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function findAllForOptions(): Collection
    {
        return Vehicle::query()
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'model', 'exit_date', 'exit_reason']);
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

    public function findByIdWithFiscalHistory(int $id): Vehicle
    {
        return Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->orderByDesc('effective_from')])
            ->findOrFail($id);
    }

    public function findAllForHeatmap(int $year): Collection
    {
        $startOfYear = CarbonImmutable::create($year, 1, 1);

        return Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->activeAt($startOfYear)
            ->orderBy('license_plate')
            ->get();
    }

    public function countActive(): int
    {
        return Vehicle::query()->whereNull('exit_date')->count();
    }

    public function findFirstRegistrationYearBounds(): ?array
    {
        $row = Vehicle::query()
            ->selectRaw('MIN(YEAR(first_french_registration_date)) AS min_year, MAX(YEAR(first_french_registration_date)) AS max_year')
            ->first()
            ?->toArray();

        if ($row === null || $row['min_year'] === null || $row['max_year'] === null) {
            return null;
        }

        return [
            'min' => (int) $row['min_year'],
            'max' => (int) $row['max_year'],
        ];
    }
}
