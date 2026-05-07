<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
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
        return FiscalDeclaration::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->where('is_obsolete', false)
            ->first();
    }

    public function findHistoryForCompanyYear(int $companyId, int $year): Collection
    {
        return FiscalDeclaration::query()
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
            ->with('company:id,short_code,legal_name,color');

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

        match ($query->sortKey) {
            'company' => $eloquentQuery
                ->leftJoin('companies', 'fiscal_declarations.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'fiscalYear' => $eloquentQuery->orderBy('fiscal_declarations.fiscal_year', $direction),
            'status' => $eloquentQuery->orderBy('fiscal_declarations.status', $direction),
            'generatedAt' => $eloquentQuery->orderBy('fiscal_declarations.generated_at', $direction),
            // Défaut : plus récente en premier (year DESC, company ASC).
            default => $eloquentQuery
                ->orderByDesc('fiscal_declarations.fiscal_year')
                ->orderBy('fiscal_declarations.company_id')
                ->orderBy('fiscal_declarations.id'),
        };

        return $eloquentQuery;
    }
}
