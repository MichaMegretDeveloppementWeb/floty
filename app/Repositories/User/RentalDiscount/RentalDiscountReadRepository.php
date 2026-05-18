<?php

declare(strict_types=1);

namespace App\Repositories\User\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\RentalDiscount\RentalDiscountIndexQueryData;
use App\Models\RentalDiscount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Implémentation Eloquent du contrat de lecture des réductions
 * commerciales.
 *
 * Repository sans état · singleton via
 * {@see App\Providers\RepositoryServiceProvider}.
 */
final class RentalDiscountReadRepository implements RentalDiscountReadRepositoryInterface
{
    public function findById(int $id): ?RentalDiscount
    {
        return RentalDiscount::query()
            ->with('vehicles')
            ->find($id);
    }

    public function findByIdWithTrashed(int $id): ?RentalDiscount
    {
        return RentalDiscount::query()
            ->withTrashed()
            ->with('vehicles')
            ->find($id);
    }

    public function existsAny(): bool
    {
        return RentalDiscount::query()->exists();
    }

    public function findActiveForCompanyYear(int $companyId, int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return RentalDiscount::query()
            ->with('vehicles')
            ->forCompany($companyId)
            ->overlappingPeriod($yearStart, $yearEnd)
            ->orderBy('start_date')
            ->get();
    }

    public function findActiveForCompaniesYear(array $companyIds, int $year): Collection
    {
        if ($companyIds === []) {
            return RentalDiscount::query()->whereRaw('1 = 0')->get();
        }

        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return RentalDiscount::query()
            ->with('vehicles')
            ->whereIn('company_id', $companyIds)
            ->overlappingPeriod($yearStart, $yearEnd)
            ->orderBy('company_id')
            ->orderBy('start_date')
            ->get();
    }

    public function findOverlappingForCompany(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): Collection {
        $query = RentalDiscount::query()
            ->with('vehicles')
            ->forCompany($companyId)
            ->overlappingPeriod($startDate, $endDate);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->orderBy('start_date')->get();
    }

    public function findActiveListingVehicleOn(int $vehicleId, string $date): Collection
    {
        // Une réduction « liste explicitement » un véhicule si la table
        // pivot contient l'association. Les réductions « tous véhicules »
        // (pivot vide) ne sont **pas** retournées · cf. doc-block de
        // l'interface (sémantique du check de suppression véhicule).
        return RentalDiscount::query()
            ->with('vehicles')
            ->activeOn($date)
            ->whereHas('vehicles', static function ($q) use ($vehicleId): void {
                $q->where('vehicles.id', $vehicleId);
            })
            ->orderBy('start_date')
            ->get();
    }

    public function findForCompany(int $companyId): Collection
    {
        return RentalDiscount::query()
            ->with(['vehicles', 'company:id,short_code,legal_name,color'])
            ->forCompany($companyId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    public function paginateForIndex(RentalDiscountIndexQueryData $query): LengthAwarePaginator
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';
        $today = now()->toDateString();

        $eloquent = RentalDiscount::query()
            ->select('rental_discounts.*')
            ->with(['vehicles', 'company:id,short_code,legal_name,color']);

        if ($query->companyId !== null) {
            $eloquent->where('rental_discounts.company_id', $query->companyId);
        }

        // Search LIKE sur label + company short_code + legal_name.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquent->where(function (Builder $w) use ($term): void {
                $w->where('rental_discounts.label', 'like', $term)
                    ->orWhereHas('company', fn (Builder $qc) => $qc
                        ->where('short_code', 'like', $term)
                        ->orWhere('legal_name', 'like', $term));
            });
        }

        if ($query->status === 'active') {
            $eloquent->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        } elseif ($query->status === 'planned') {
            $eloquent->whereDate('start_date', '>', $today);
        } elseif ($query->status === 'expired') {
            $eloquent->whereDate('end_date', '<', $today);
        }

        match ($query->sortKey) {
            'company' => $eloquent
                ->leftJoin('companies', 'rental_discounts.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'period' => $eloquent
                ->orderBy('rental_discounts.start_date', $direction)
                ->orderBy('rental_discounts.end_date', $direction),
            'discount' => $eloquent->orderBy('rental_discounts.discount_basis_points', $direction),
            'createdAt' => $eloquent->orderBy('rental_discounts.created_at', $direction),
            // Défaut · plus récente en premier (start_date DESC).
            default => $eloquent
                ->orderByDesc('rental_discounts.start_date')
                ->orderByDesc('rental_discounts.id'),
        };

        return $eloquent->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function findByIdForShow(int $id): ?RentalDiscount
    {
        return RentalDiscount::query()
            ->with([
                'vehicles' => fn ($q) => $q->orderBy('license_plate'),
                'company:id,short_code,legal_name,color',
                'createdBy:id,first_name,last_name',
            ])
            ->withCount('invoiceLines')
            ->find($id);
    }

    public function statsForIndex(string $today): array
    {
        // 3 sous-requêtes scalaires · payload léger, 1 round-trip BDD
        // groupé sur le serveur SQL via aggregat unique avec CASE.
        $row = RentalDiscount::query()
            ->selectRaw(
                'SUM(CASE WHEN start_date <= ? AND end_date >= ? THEN 1 ELSE 0 END) as active_count,'
                .' SUM(CASE WHEN start_date > ? THEN 1 ELSE 0 END) as planned_count,'
                .' SUM(CASE WHEN end_date < ? THEN 1 ELSE 0 END) as expired_count',
                [$today, $today, $today, $today],
            )
            ->first();

        return [
            'active' => (int) ($row->active_count ?? 0),
            'planned' => (int) ($row->planned_count ?? 0),
            'expired' => (int) ($row->expired_count ?? 0),
        ];
    }
}
