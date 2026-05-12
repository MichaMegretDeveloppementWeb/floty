<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationData;
use App\Data\User\FiscalDeclaration\ReviewClusterData;
use App\Models\FiscalReviewDecision;
use RuntimeException;

/**
 * Orchestrateur de la preview d'une déclaration fiscale (Phase 11 D3,
 * ADR-0015 § 6.1).
 *
 * Pipeline :
 *   1. Détection des clusters (D2 · `RiskDetectionService`)
 *   2. Pré-application des décisions persistées par fingerprint
 *      (cf. ADR § D5 + § 6.5 reprise auto à la régénération)
 *   3. Lookup de la déclaration active courante (D1)
 *   4. Comptage des clusters pending = bloqueurs de génération
 *
 * Service stateless, pure lecture. Aucune persistance ; les
 * mutations vivent dans les Actions D3.B.
 */
final readonly class DeclarationPreviewService
{
    public function __construct(
        private RiskDetectionService $detection,
        private FiscalReviewDecisionReadRepositoryInterface $decisions,
        private FiscalDeclarationReadRepositoryInterface $declarations,
        private CompanyReadRepositoryInterface $companies,
    ) {}

    public function preview(int $companyId, int $year): DeclarationPreviewData
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            throw new RuntimeException(sprintf('Entreprise %d introuvable.', $companyId));
        }

        $rawClusters = $this->detection->detectClusters($companyId, $year);
        $persistedDecisions = $this->decisions->findAllForCompanyYear($companyId, $year);

        $enrichedClusters = $this->applyPersistedDecisions($rawClusters, $persistedDecisions);

        $pending = 0;
        foreach ($enrichedClusters as $cluster) {
            if ($cluster->decision === null) {
                $pending++;
            }
        }

        $activeDeclaration = $this->declarations->findActiveForCompanyYear($companyId, $year);

        return new DeclarationPreviewData(
            companyId: $company->id,
            companyShortCode: $company->short_code,
            companyLegalName: $company->legal_name,
            fiscalYear: $year,
            clusters: $enrichedClusters,
            pendingClustersCount: $pending,
            canGenerate: $pending === 0,
            declaration: $activeDeclaration !== null
                ? FiscalDeclarationData::fromModel($activeDeclaration->load('company'))
                : null,
        );
    }

    /**
     * Pour chaque cluster, lookup la décision persistée par fingerprint
     * (ADR-0015 § D5). Si match : injecte `decision` + `justification`
     * dans un nouveau `ReviewClusterData` immutable.
     *
     * @param  list<ReviewClusterData>  $clusters
     * @param  iterable<FiscalReviewDecision>  $persistedDecisions
     * @return list<ReviewClusterData>
     */
    private function applyPersistedDecisions(array $clusters, iterable $persistedDecisions): array
    {
        $byFingerprint = [];
        foreach ($persistedDecisions as $decision) {
            $byFingerprint[$decision->cluster_fingerprint] = $decision;
        }

        $enriched = [];
        foreach ($clusters as $cluster) {
            $match = $byFingerprint[$cluster->fingerprint] ?? null;
            if ($match === null) {
                $enriched[] = $cluster;

                continue;
            }

            $enriched[] = new ReviewClusterData(
                code: $cluster->code,
                level: $cluster->level,
                fingerprint: $cluster->fingerprint,
                contracts: $cluster->contracts,
                contractsCount: $cluster->contractsCount,
                cumulativeDaysInYear: $cluster->cumulativeDaysInYear,
                decision: $match->decision,
                justification: $match->justification,
            );
        }

        return $enriched;
    }
}
