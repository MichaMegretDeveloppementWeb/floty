<?php

declare(strict_types=1);

namespace App\Repositories\User\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * Implémentation Eloquent des lectures Company.
 */
final class CompanyReadRepository implements CompanyReadRepositoryInterface
{
    public function findAllOrderedByName(): Collection
    {
        return Company::query()->orderBy('legal_name')->get();
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
}
