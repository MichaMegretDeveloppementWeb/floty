<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalReviewDecision;

use App\Models\FiscalReviewDecision;
use Illuminate\Database\Eloquent\Collection;

/**
 * FiscalReviewDecision reads (ADR-0015 § 5.2).
 *
 * Functional identity: `(company, year, cluster_fingerprint)`. The
 * fingerprint is the deterministic hash of a risk cluster, allowing
 * cross-regeneration persistence (if the cluster still exists after a
 * recompute, its decision is automatically rehydrated).
 */
interface FiscalReviewDecisionReadRepositoryInterface
{
    public function findByFingerprint(int $companyId, int $year, string $fingerprint): ?FiscalReviewDecision;

    /**
     * All decisions (kept + requalified) for the `(company, year)`
     * couple. Used by the review engine to reinject persisted decisions
     * onto freshly detected clusters.
     *
     * @return Collection<int, FiscalReviewDecision>
     */
    public function findAllForCompanyYear(int $companyId, int $year): Collection;
}
