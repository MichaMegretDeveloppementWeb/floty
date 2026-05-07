<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Collection;

final class FiscalDeclarationReadRepository implements FiscalDeclarationReadRepositoryInterface
{
    public function findById(int $id): ?FiscalDeclaration
    {
        return FiscalDeclaration::query()->find($id);
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
}
