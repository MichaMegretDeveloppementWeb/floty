<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class FiscalDeclarationReadRepository implements FiscalDeclarationReadRepositoryInterface
{
    public function findById(int $id): ?FiscalDeclaration
    {
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->find($id);
    }

    public function findActiveForCompanyYear(int $companyId, int $year): ?FiscalDeclaration
    {
        // `orderByDesc('id')` guarantees determinism in the
        // pathological case where two active rows coexist for the same
        // couple. In production this state is now impossible by
        // construction thanks to the partial UNIQUE index introduced
        // by the `add_unique_index_to_fiscal_declarations` migration
        // (MySQL 8 virtual column `active_uniqueness_key`), but the
        // order is kept here as defence in depth (and for tests that
        // could still create duplicates via factory bypassing the
        // constraint by mistake).
        //
        // Eager loading `company` aligned with
        // `findCurrentForCompanyYear` to avoid N+1 in consumers.
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->where('is_obsolete', false)
            ->orderByDesc('id')
            ->first();
    }

    public function findCurrentForCompanyYear(int $companyId, int $year): ?FiscalDeclaration
    {
        // Head of the chain · last link, the declaration that points
        // to no other via `superseded_by_id` (so has not yet been
        // replaced). An orphan obsolete declaration remains head until
        // a regeneration Draft is created and assigns
        // `superseded_by_id`, at which point the Draft becomes the
        // new head.
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->whereNull('superseded_by_id')
            ->orderByDesc('id')
            ->first();
    }

    public function findPredecessorOf(int $declarationId): ?FiscalDeclaration
    {
        // Declaration X such that X.superseded_by_id = $declarationId.
        // In practice a single one (linear chain old → new).
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('superseded_by_id', $declarationId)
            ->orderByDesc('id')
            ->first();
    }

    public function findHistoryForCompanyYear(int $companyId, int $year): Collection
    {
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->orderBy('id')
            ->get();
    }

    public function paginateForIndex(DeclarationIndexQueryData $query): LengthAwarePaginator
    {
        return $this->buildIndexQuery($query)->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function existsAny(): bool
    {
        return FiscalDeclaration::query()->exists();
    }

    /**
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array
    {
        /** @var object{min_year: int|null, max_year: int|null}|null $row */
        $row = FiscalDeclaration::query()
            ->selectRaw('MIN(fiscal_year) as min_year, MAX(fiscal_year) as max_year')
            ->first();

        if ($row === null || $row->min_year === null) {
            return null;
        }

        return [
            'min' => (int) $row->min_year,
            'max' => (int) $row->max_year,
        ];
    }

    public function findCompanyOptions(): Collection
    {
        return Company::query()
            ->whereIn('id', FiscalDeclaration::query()->select('company_id'))
            ->orderBy('short_code')
            ->get();
    }

    /**
     * @return Builder<FiscalDeclaration>
     */
    private function buildIndexQuery(DeclarationIndexQueryData $query): Builder
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = FiscalDeclaration::query()
            ->select('fiscal_declarations.*')
            ->with([
                'company:id,short_code,legal_name,color',
                // Loads the successor so we can distinguish an orphan
                // obsolete from a chained Draft in the Index status
                // pill; `reference` feeds the sub-mentions
                // "Regeneration in progress · DECL-XXX" and "Replaced
                // by DECL-XXX".
                'supersededBy:id,status,reference',
                // Loads the predecessor to render the sub-mention
                // "Replaces DECL-XXX" on the Index.
                'supersedes:id,status,reference,superseded_by_id',
            ]);

        if ($query->companyId !== null) {
            $eloquentQuery->where('fiscal_declarations.company_id', $query->companyId);
        }
        if ($query->fiscalYear !== null) {
            $eloquentQuery->where('fiscal_declarations.fiscal_year', $query->fiscalYear);
        }
        if ($query->status !== null) {
            $eloquentQuery->where('fiscal_declarations.status', $query->status->value);
        }
        if ($query->obsoleteOnly) {
            $eloquentQuery->where('fiscal_declarations.is_obsolete', true);
        }

        // Reference search with chain expansion (two-branch algorithm).
        // The user types a reference (fragment of DECL-XXX-YYYY-NNNN)
        // OR a draft label ("Brouillon #4" / "#4" / "4"). The matching
        // `(company_id, fiscal_year)` couples are identified, then all
        // declarations of those couples are returned (chain expansion).
        //
        // Branch Q1 · LIKE %term% on the `reference` column. The Floty
        // fiscal domain is structurally bounded (~300 rows max over 10
        // years for a client), so a full scan is sub-100ms.
        //
        // Branch Q2 · if `term` matches a draft pattern, direct PK
        // lookup on the extracted id (instantaneous).
        if ($query->search !== null && trim($query->search) !== '') {
            $term = trim($query->search);

            // Q1 · couples with a matching reference. `toBase()` so
            // `merge()` uses Illuminate Collection deduplication (by
            // keys) instead of Eloquent Collection (by PK, which would
            // require `id` in the select).
            $pairsFromReference = FiscalDeclaration::query()
                ->whereNotNull('reference')
                ->where('reference', 'LIKE', '%'.$term.'%')
                ->select('company_id', 'fiscal_year')
                ->distinct()
                ->get()
                ->toBase();

            $pairsFromId = collect();
            if (preg_match('/^(?:[Bb]rouillon\s*)?#?(\d+)$/', $term, $matches) === 1) {
                $extractedId = (int) $matches[1];
                $pairsFromId = FiscalDeclaration::query()
                    ->whereKey($extractedId)
                    ->select('company_id', 'fiscal_year')
                    ->get()
                    ->toBase();
            }

            $pairs = $pairsFromReference
                ->merge($pairsFromId)
                ->unique(fn ($p): string => $p->company_id.'|'.$p->fiscal_year);

            if ($pairs->isEmpty()) {
                $eloquentQuery->whereRaw('1 = 0');
            } else {
                $eloquentQuery->where(function ($q) use ($pairs): void {
                    foreach ($pairs as $pair) {
                        $q->orWhere(function ($q2) use ($pair): void {
                            $q2->where('fiscal_declarations.company_id', $pair->company_id)
                                ->where('fiscal_declarations.fiscal_year', $pair->fiscal_year);
                        });
                    }
                });
            }
        }

        match ($query->sortKey) {
            'company' => $eloquentQuery
                ->leftJoin('companies', 'fiscal_declarations.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'fiscalYear' => $eloquentQuery->orderBy('fiscal_declarations.fiscal_year', $direction),
            'reference' => $eloquentQuery->orderBy('fiscal_declarations.reference', $direction),
            'status' => $eloquentQuery->orderBy('fiscal_declarations.status', $direction),
            'generatedAt' => $eloquentQuery->orderBy('fiscal_declarations.generated_at', $direction),
            // Default: newest first (year DESC, company ASC).
            default => $eloquentQuery
                ->orderByDesc('fiscal_declarations.fiscal_year')
                ->orderBy('fiscal_declarations.company_id')
                ->orderBy('fiscal_declarations.id'),
        };

        return $eloquentQuery;
    }

    public function findGeneratedForCompanyYears(array $companyIds, array $years): Collection
    {
        if ($companyIds === [] || $years === []) {
            return new Collection;
        }

        return FiscalDeclaration::query()
            ->with('company:id,short_code,legal_name')
            ->whereIn('company_id', $companyIds)
            ->whereIn('fiscal_year', $years)
            ->where('status', FiscalDeclarationStatus::Generated)
            ->get();
    }

    public function countWithTrashedForReference(int $companyId, int $year): int
    {
        return FiscalDeclaration::withTrashed()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->whereNotNull('reference')
            ->lockForUpdate()
            ->count();
    }
}
