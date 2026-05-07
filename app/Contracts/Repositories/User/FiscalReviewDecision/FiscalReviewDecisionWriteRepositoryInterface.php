<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalReviewDecision;

use App\Models\FiscalReviewDecision;

/**
 * Écritures FiscalReviewDecision (Phase 11 D1, ADR-0015 § 5.2).
 *
 * Une décision n'est jamais « update » au sens classique : un
 * utilisateur peut changer d'avis sur le même cluster (même fingerprint),
 * mais la base ne conserve qu'une ligne par `(company, year, fingerprint)`.
 * Pattern `upsert` aligne ce comportement.
 */
interface FiscalReviewDecisionWriteRepositoryInterface
{
    /**
     * Crée ou remplace la décision identifiée par
     * `(company_id, fiscal_year, cluster_fingerprint)`. Les attributs
     * d'audit (`decided_by`, `decided_at`) sont posés à chaque appel.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes): FiscalReviewDecision;
}
