<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Company;

use App\Data\User\Company\CompanyIndexQueryData;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads on the Company domain.
 *
 * All Eloquent queries targeting {@see Company} live here. Services
 * consume these methods to orchestrate business logic without touching
 * `Company::query()` directly.
 */
interface CompanyReadRepositoryInterface
{
    public function findById(int $id): ?Company;

    /**
     * All companies sorted by legal name.
     *
     * @return Collection<int, Company>
     *
     * @deprecated Use {@see paginateForIndex()} instead.
     */
    public function findAllOrderedByName(): Collection;

    /**
     * Server-side paginated list for the Companies Index (ADR-0020).
     * Applies `{search, isActive, sortKey, sortDirection, page,
     * perPage}` from the DTO as raw SQL.
     *
     * Search: LIKE on `short_code OR legal_name OR siren`.
     * Sort whitelist: shortCode | legalName | siren | city.
     *
     * @return LengthAwarePaginator<int, Company>
     */
    public function paginateForIndex(CompanyIndexQueryData $query): LengthAwarePaginator;

    /**
     * Returns true iff at least one company exists (`SELECT EXISTS`).
     * Used by the Index to distinguish an intrinsically empty table
     * from an active filter returning zero rows.
     */
    public function existsAny(): bool;

    /**
     * Active companies for `<SelectInput>` widgets, minimal columns,
     * sorted by legal name.
     *
     * @return Collection<int, Company>
     */
    public function findAllForOptions(): Collection;

    /**
     * Active companies for the planning heatmap, minimal columns,
     * sorted by short code.
     *
     * @return Collection<int, Company>
     */
    public function findAllForHeatmap(): Collection;

    /**
     * Counts active companies.
     */
    public function countActive(): int;

    /**
     * Bulk loads companies by ids, indexed by id. Includes columns
     * needed for display (legal name, short code, color). Returns an
     * empty collection if `$ids` is empty.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Company>
     */
    public function findByIdsIndexed(array $ids): Collection;

    /**
     * Checks whether a short code is already used by a non-deleted
     * company. Used by CreateCompanyAction for the pre-insert uniqueness
     * check (short_code is auto-generated).
     */
    public function existsByShortCode(string $shortCode): bool;
}
