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
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Orchestrates the declaration preview (ADR-0015 § 6.1).
 *
 * Pipeline · detect risk clusters, apply persisted decisions keyed by
 * fingerprint, look up the current active declaration, count pending
 * clusters (generation blockers). Stateless and read-only; mutations
 * live in the dedicated Actions.
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
     * Injects persisted decisions onto matching clusters by fingerprint
     * (ADR-0015 § D5).
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

            $excluded = array_values(array_map(
                static fn ($v): int => (int) $v,
                $match->excluded_contract_ids ?? [],
            ));

            if ($excluded === []) {
                $enriched[] = new ReviewClusterData(
                    code: $cluster->code,
                    level: $cluster->level,
                    fingerprint: $cluster->fingerprint,
                    contracts: $cluster->contracts,
                    contractsCount: $cluster->contractsCount,
                    coveragePeriodDays: $cluster->coveragePeriodDays,
                    coverageStartDate: $cluster->coverageStartDate,
                    coverageEndDate: $cluster->coverageEndDate,
                    distinctVehiclesCount: $cluster->distinctVehiclesCount,
                    decision: $match->decision,
                    justification: $match->justification,
                    excludedContractIds: [],
                );

                continue;
            }

            // Recompute effective stats with the excluded contracts
            // removed; the raw `contracts` list is preserved so the
            // modal can toggle each one individually.
            $stats = $this->computeEffectiveStats($cluster, $excluded);

            $enriched[] = new ReviewClusterData(
                code: $cluster->code,
                level: $cluster->level,
                fingerprint: $cluster->fingerprint,
                contracts: $cluster->contracts,
                contractsCount: $stats['count'],
                coveragePeriodDays: $stats['days'],
                coverageStartDate: $stats['start'],
                coverageEndDate: $stats['end'],
                distinctVehiclesCount: $stats['vehicles'],
                decision: $match->decision,
                justification: $match->justification,
                excludedContractIds: $excluded,
            );
        }

        return $enriched;
    }

    /**
     * Recomputes cluster stats after excluding contracts (those are
     * treated as standalone LCD outside the chain). Degenerate case of
     * everything excluded falls back to zeros with raw cluster dates.
     *
     * @param  list<int>  $excludedContractIds
     * @return array{count: int, days: int, start: string, end: string, vehicles: int}
     */
    private function computeEffectiveStats(ReviewClusterData $cluster, array $excludedContractIds): array
    {
        $included = array_values(array_filter(
            $cluster->contracts,
            static fn ($c) => ! in_array($c->contractId, $excludedContractIds, true),
        ));

        if ($included === []) {
            return [
                'count' => 0,
                'days' => 0,
                'start' => $cluster->coverageStartDate,
                'end' => $cluster->coverageEndDate,
                'vehicles' => 0,
            ];
        }

        $minStart = $included[0]->startDate;
        $maxEnd = $included[0]->endDate;
        foreach ($included as $c) {
            if (strcmp($c->startDate, $minStart) < 0) {
                $minStart = $c->startDate;
            }
            if (strcmp($c->endDate, $maxEnd) > 0) {
                $maxEnd = $c->endDate;
            }
        }

        $effectiveDays = (int) CarbonImmutable::parse($minStart)
            ->diffInDays(CarbonImmutable::parse($maxEnd)) + 1;

        $vehicleIds = array_unique(array_map(
            static fn ($c): int => $c->vehicleId,
            $included,
        ));

        return [
            'count' => count($included),
            'days' => $effectiveDays,
            'start' => $minStart,
            'end' => $maxEnd,
            'vehicles' => count($vehicleIds),
        ];
    }
}
