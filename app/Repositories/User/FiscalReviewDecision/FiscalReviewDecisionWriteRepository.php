<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalReviewDecision;

use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepositoryInterface;
use App\Models\FiscalReviewDecision;

final class FiscalReviewDecisionWriteRepository implements FiscalReviewDecisionWriteRepositoryInterface
{
    public function upsert(array $attributes): FiscalReviewDecision
    {
        return FiscalReviewDecision::query()->updateOrCreate(
            [
                'company_id' => $attributes['company_id'],
                'fiscal_year' => $attributes['fiscal_year'],
                'cluster_fingerprint' => $attributes['cluster_fingerprint'],
            ],
            $attributes,
        );
    }
}
