<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalReviewDecision;

use App\Models\FiscalReviewDecision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures FiscalReviewDecision (Phase 11 D1, ADR-0015 § 5.2).
 *
 * Identité fonctionnelle : `(company, year, cluster_fingerprint)`. Le
 * fingerprint est le hash déterministe d'un cluster de risque, ce qui
 * permet la persistance « cross-régénération » (D3 : si le cluster
 * existe toujours après recalcul, sa décision est auto-reprise).
 */
interface FiscalReviewDecisionReadRepositoryInterface
{
    public function findByFingerprint(int $companyId, int $year, string $fingerprint): ?FiscalReviewDecision;

    /**
     * Toutes les décisions (conservées + requalifiées) pour le couple
     * `(company, year)`. Sert au moteur de revue D3 pour réinjecter les
     * décisions persistées sur les clusters fraîchement détectés.
     *
     * @return Collection<int, FiscalReviewDecision>
     */
    public function findAllForCompanyYear(int $companyId, int $year): Collection;
}
