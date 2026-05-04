<?php

declare(strict_types=1);

namespace App\Repositories\User\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Implémentation Eloquent des lectures Company.
 */
final class CompanyReadRepository implements CompanyReadRepositoryInterface
{
    public function findById(int $id): ?Company
    {
        return Company::query()->find($id);
    }

    public function findAllOrderedByName(): Collection
    {
        return Company::query()->orderBy('legal_name')->get();
    }

    public function paginateForIndex(CompanyIndexQueryData $query): LengthAwarePaginator
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Company::query();

        if ($query->isActive !== null) {
            $eloquentQuery->where('is_active', $query->isActive);
        }

        if ($query->contractsScope === 'with') {
            $eloquentQuery->has('contracts');
        } elseif ($query->contractsScope === 'without') {
            $eloquentQuery->doesntHave('contracts');
        }

        if ($query->companyType === 'corporate') {
            $eloquentQuery->where('is_individual_business', false);
        } elseif ($query->companyType === 'individual') {
            $eloquentQuery->where('is_individual_business', true);
        }

        if ($query->city !== null && $query->city !== '') {
            $eloquentQuery->where('city', 'like', '%'.$query->city.'%');
        }

        // Search LIKE sur short_code OR legal_name OR siren.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function ($w) use ($term): void {
                $w->where('short_code', 'like', $term)
                    ->orWhere('legal_name', 'like', $term)
                    ->orWhere('siren', 'like', $term);
            });
        }

        // Tri whitelist (cf. CompanyIndexQueryData::allowedSortKeys()).
        match ($query->sortKey) {
            'shortCode' => $eloquentQuery->orderBy('short_code', $direction),
            'legalName' => $eloquentQuery->orderBy('legal_name', $direction),
            'siren' => $eloquentQuery->orderBy('siren', $direction),
            'city' => $eloquentQuery->orderBy('city', $direction),
            default => $eloquentQuery->orderBy('legal_name'),
        };

        return $eloquentQuery->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function findAllForOptions(): Collection
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'short_code', 'color']);
    }

    public function findAllForHeatmap(): Collection
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('short_code')
            ->get(['id', 'short_code', 'legal_name', 'color']);
    }

    public function countActive(): int
    {
        return Company::query()->where('is_active', true)->count();
    }

    public function findByIdsIndexed(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Company::query()
            ->whereIn('id', $ids)
            ->get(['id', 'short_code', 'legal_name', 'color'])
            ->keyBy('id');
    }

    public function existsByShortCode(string $shortCode): bool
    {
        return Company::query()->where('short_code', $shortCode)->exists();
    }
}
