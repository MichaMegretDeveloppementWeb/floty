<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\ReviewClusterData;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Models\FiscalReviewDecision;

/**
 * Resolver for fiscal-review human decisions, extracted from
 * `DeclarationFiscalEngine` for SRP.
 *
 * Three responsibilities of cluster ↔ decision matching ·
 *   - {@see buildAppliedDecisionsAndOptOuts()} · transforms DB
 *     decisions into `AppliedDecisionEntry` and extracts opt-out
 *     contractIds (non-excluded contracts of a Requalified cluster)
 *     for the runtime LCD decorator.
 *   - {@see resolveRetainedFromMap()} · identifies fingerprint-reused
 *     decisions inherited from the predecessor during regeneration
 *     (frontend badge).
 *   - {@see buildContractClusterMap()} · builds the
 *     `contractId => clusterInfo` map enriching every
 *     `ContractSnapshotEntry` with its cluster + decision +
 *     `retainedFrom`.
 *
 * Pure matching logic · no fiscal computation, no pipeline call. The
 * only side effect is reading declarations to resolve the predecessor
 * chain.
 */
final readonly class DeclarationDecisionResolver
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    /**
     * Matches decisions ↔ clusters by fingerprint. Mirrors
     * {@see App\Services\Fiscal\RiskDetection\DeclarationPreviewService::applyPersistedDecisions()}.
     *
     * @param  list<ReviewClusterData>  $clusters
     * @param  iterable<FiscalReviewDecision>  $persistedDecisions
     * @return array{0: list<int>, 1: list<AppliedDecisionEntry>}
     */
    public function buildAppliedDecisionsAndOptOuts(
        array $clusters,
        iterable $persistedDecisions,
    ): array {
        $byFingerprint = [];
        foreach ($persistedDecisions as $decision) {
            $byFingerprint[$decision->cluster_fingerprint] = $decision;
        }

        $optOuts = [];
        $applied = [];
        foreach ($clusters as $cluster) {
            $match = $byFingerprint[$cluster->fingerprint] ?? null;
            if ($match === null) {
                continue;
            }

            $contractIds = array_map(
                static fn ($contract): int => $contract->contractId,
                $cluster->contracts,
            );

            // Contracts explicitly excluded from the cluster are
            // treated as standalone LCD exempted by R-2024-021 and do
            // not participate in the opt-out, even when the global
            // decision is Requalified.
            $excludedContractIds = array_values(array_map(
                static fn ($v): int => (int) $v,
                $match->excluded_contract_ids ?? [],
            ));

            $applied[] = new AppliedDecisionEntry(
                clusterFingerprint: $cluster->fingerprint,
                riskCode: $cluster->code,
                decision: $match->decision,
                contractIds: $contractIds,
                justification: $match->justification,
                excludedContractIds: $excludedContractIds,
            );

            if ($match->decision === ReviewDecisionType::Requalified) {
                foreach ($contractIds as $contractId) {
                    if (in_array($contractId, $excludedContractIds, true)) {
                        continue;
                    }
                    $optOuts[] = $contractId;
                }
            }
        }

        $optOuts = array_values(array_unique($optOuts));
        sort($optOuts);

        return [$optOuts, $applied];
    }

    /**
     * Resolves the "decisions reused from the predecessor by
     * fingerprint" map.
     *
     * A decision is reused iff the current declaration of
     * `(company, year)` has a predecessor (regeneration in progress or
     * complete) AND the fingerprint-matched decision was persisted
     * before the current declaration was created
     * (`decided_at < currentDeclaration.created_at`).
     *
     * Returns `fingerprint => predecessorDeclarationId` so the frontend
     * can show the « Décision reprise » badge. Empty map when the
     * current declaration is not a regeneration (first preparation) or
     * when no decision predates the current session.
     *
     * @param  iterable<FiscalReviewDecision>  $persistedDecisions
     * @return array<string, int>
     */
    public function resolveRetainedFromMap(
        int $companyId,
        int $year,
        iterable $persistedDecisions,
    ): array {
        $current = $this->declarations->findCurrentForCompanyYear($companyId, $year);
        if ($current === null) {
            return [];
        }

        $predecessor = $this->declarations->findPredecessorOf($current->id);
        if ($predecessor === null) {
            return [];
        }

        $currentCreatedAt = $current->created_at;
        if ($currentCreatedAt === null) {
            return [];
        }

        $retainedMap = [];
        foreach ($persistedDecisions as $decision) {
            if ($decision->decided_at->lessThan($currentCreatedAt)) {
                $retainedMap[$decision->cluster_fingerprint] = $predecessor->id;
            }
        }

        return $retainedMap;
    }

    /**
     * Builds the `contractId => clusterInfo` map to enrich every
     * {@see App\Fiscal\ValueObjects\ContractSnapshotEntry} with its
     * cluster, persisted decision (if any) and `retainedFrom` flag.
     *
     * @param  list<ReviewClusterData>  $clusters
     * @param  list<AppliedDecisionEntry>  $appliedDecisions
     * @param  array<string, int>  $retainedFromByFingerprint  fingerprint → predecessor declaration id
     * @return array<int, array{fingerprint: string, riskCode: RiskCode, riskLevel: RiskLevel, decision: ?ReviewDecisionType, justification: ?string, retainedFrom: ?int}>
     */
    public function buildContractClusterMap(
        array $clusters,
        array $appliedDecisions,
        array $retainedFromByFingerprint,
    ): array {
        $decisionsByFingerprint = [];
        foreach ($appliedDecisions as $applied) {
            $decisionsByFingerprint[$applied->clusterFingerprint] = $applied;
        }

        $map = [];
        foreach ($clusters as $cluster) {
            $applied = $decisionsByFingerprint[$cluster->fingerprint] ?? null;
            $retainedFrom = $retainedFromByFingerprint[$cluster->fingerprint] ?? null;
            $excludedIds = $applied !== null ? $applied->excludedContractIds : [];
            foreach ($cluster->contracts as $clusterContract) {
                // Contracts explicitly excluded by the user leave the
                // cluster on the snapshot side · no mapping →
                // `clusterFingerprint=null` on `ContractSnapshotEntry`,
                // rendered as a plain row outside the cluster block.
                if (in_array($clusterContract->contractId, $excludedIds, true)) {
                    continue;
                }

                $map[$clusterContract->contractId] = [
                    'fingerprint' => $cluster->fingerprint,
                    'riskCode' => $cluster->code,
                    'riskLevel' => $cluster->level,
                    'decision' => $applied?->decision,
                    'justification' => $applied?->justification,
                    'retainedFrom' => $retainedFrom,
                ];
            }
        }

        return $map;
    }
}
