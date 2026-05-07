<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalReviewDecision;

use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Models\FiscalReviewDecision;
use Illuminate\Database\Eloquent\Collection;

final class FiscalReviewDecisionReadRepository implements FiscalReviewDecisionReadRepositoryInterface
{
    public function findByFingerprint(int $companyId, int $year, string $fingerprint): ?FiscalReviewDecision
    {
        return FiscalReviewDecision::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->where('cluster_fingerprint', $fingerprint)
            ->first();
    }

    public function findAllForCompanyYear(int $companyId, int $year): Collection
    {
        return FiscalReviewDecision::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->orderBy('id')
            ->get();
    }
}
