<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalDeclaration;

use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * FiscalDeclaration reads (ADR-0015 § 5.1 rev. 1.1).
 *
 * "Active" = non obsolete. Several declarations can coexist for a
 * `(company, year)` couple through the obsolescence chain; only one at
 * a time is active (invariant guaranteed by Actions / observers, not
 * by a SQL constraint).
 */
interface FiscalDeclarationReadRepositoryInterface
{
    public function findById(int $id): ?FiscalDeclaration;

    /**
     * Active (non obsolete) declaration for `(company, year)`.
     */
    public function findActiveForCompanyYear(int $companyId, int $year): ?FiscalDeclaration;

    /**
     * "Current" declaration for `(company, year)`: the most advanced
     * version of the chain, even when obsolete. Differs from
     * `findActiveForCompanyYear` which filters `is_obsolete = false`
     * and hides orphaned obsolete declarations.
     *
     * Definition: the current declaration is the head of the
     * `superseded_by_id` chain, i.e. the last link
     * (`superseded_by_id IS NULL`), the version not yet replaced by
     * anyone. If a regeneration is in progress (a chained Draft), this
     * Draft is current. Otherwise it is the latest Generated (active
     * or orphan obsolete) or an initial Draft.
     *
     * Returns null when no declaration exists for the couple.
     */
    public function findCurrentForCompanyYear(int $companyId, int $year): ?FiscalDeclaration;

    /**
     * Immediate predecessor in the `superseded_by_id` chain. If
     * `$declaration` is a chained Draft being regenerated, returns the
     * obsolete version it replaces. Useful for `<ReviewContextBanner>`
     * and `<DeclarationStateCard>`.
     */
    public function findPredecessorOf(int $declarationId): ?FiscalDeclaration;

    /**
     * Chronological history (oldest → newest) of declarations for the
     * couple, obsolete included. Used by the company page and the Show
     * page for the audit trace.
     *
     * @return Collection<int, FiscalDeclaration>
     */
    public function findHistoryForCompanyYear(int $companyId, int $year): Collection;

    /**
     * Server-side paginated list for the Declarations Index (ADR-0020).
     *
     * @return LengthAwarePaginator<int, FiscalDeclaration>
     */
    public function paginateForIndex(DeclarationIndexQueryData $query): LengthAwarePaginator;

    /**
     * Returns true iff at least one declaration exists. Used by the
     * Index to distinguish an intrinsically empty table from an active
     * filter returning zero rows.
     */
    public function existsAny(): bool;

    /**
     * Bounds of years covered by declarations. Used to drive the Year
     * filter options list on the Index.
     *
     * Returns `null` if there is no declaration.
     *
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array;

    /**
     * Companies with at least one declaration. Feeds the Company
     * filter options list on the Index.
     *
     * @return Collection<int, Company>
     */
    public function findCompanyOptions(): Collection;

    /**
     * Declarations in `Generated` status matching one of the
     * `(company_id, fiscal_year)` couples of the cartesian product
     * `companyIds × years`. Eager-loads
     * `company:short_code,legal_name` for invalidation toasts.
     *
     * Used by {@see App\Services\Fiscal\Declaration\DeclarationInvalidationDetector}
     * when propagating Contract / VFC / Vehicle / Unavailability
     * mutations to emitted declarations · only Generated are marked
     * obsolete, not Draft/Deferred.
     *
     * @param  list<int>  $companyIds
     * @param  list<int>  $years
     * @return Collection<int, FiscalDeclaration>
     */
    public function findGeneratedForCompanyYears(array $companyIds, array $years): Collection;

    /**
     * Count of existing declarations (including soft-deleted) of
     * `(company_id, fiscal_year)` whose `reference` is non-null · used
     * for sequencing the readable references
     * `DECL-{shortCode}-{year}-{NNNN}` (cf.
     * {@see App\Services\Fiscal\Declaration\DeclarationReferenceGenerator}).
     *
     * Atomicity invariant: `lockForUpdate()` is applied on rows of the
     * couple during the COUNT. The caller MUST wrap in a
     * `DB::transaction(...)` otherwise the pessimistic lock is
     * ineffective.
     */
    public function countWithTrashedForReference(int $companyId, int $year): int;
}
