<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\DeclarationLifecycleStateData;
use App\Data\User\FiscalDeclaration\DeclarationListItemData;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use Illuminate\Support\Facades\Log;

/**
 * Composes the full declaration lifecycle state for a `(company, year)`
 * pair (Proposition IV).
 *
 * Centralises the derivation of {@see DeclarationLifecycleState} (S1
 * untouched through S7 regeneration in progress) from the combination
 * of `status × is_obsolete × supersededBy × predecessor`. Consumed by
 * `CompanyFiscalTab` via the adaptive `<DeclarationStateCard>`.
 *
 * Single source of truth for the Company fiche · replaces the legacy
 * `fiscalActiveDeclaration` accessor that filtered `is_obsolete = false`
 * and hid orphan obsolete declarations.
 */
final readonly class DeclarationLifecycleResolver
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $declarations,
        private FiscalReviewDecisionReadRepositoryInterface $decisions,
        private RiskDetectionService $riskDetection,
    ) {}

    public function resolveForCompanyYear(int $companyId, int $year): DeclarationLifecycleStateData
    {
        return $this->resolveFromCurrent(
            $this->declarations->findCurrentForCompanyYear($companyId, $year),
            $companyId,
            $year,
        );
    }

    /**
     * Lifecycle derivation from an already-resolved current declaration
     * (head of chain) · lets a caller batch the `findCurrentForCompanyYear`
     * query across several years and feed each result in, instead of one
     * lookup per year. `$current` null means no declaration exists
     * (Untouched). Strictly equivalent to {@see resolveForCompanyYear}
     * when fed `findCurrentForCompanyYear($companyId, $year)`.
     */
    public function resolveFromCurrent(?FiscalDeclaration $current, int $companyId, int $year): DeclarationLifecycleStateData
    {
        if ($current === null) {
            return $this->buildUntouchedState();
        }

        $predecessor = $this->declarations->findPredecessorOf($current->id);
        $hasPredecessor = $predecessor !== null;

        $pendingClustersCount = $current->status === FiscalDeclarationStatus::Draft
            ? $this->countPendingClusters($companyId, $year)
            : 0;

        $state = $this->deriveState($current, $hasPredecessor, $pendingClustersCount);

        return new DeclarationLifecycleStateData(
            state: $state,
            currentDeclaration: DeclarationListItemData::fromModel($current),
            predecessorDeclaration: $hasPredecessor
                ? DeclarationListItemData::fromModel($predecessor)
                : null,
            pendingClustersCount: $pendingClustersCount,
            canGenerate: $state === DeclarationLifecycleState::DraftReadyToGenerate,
            obsoleteReasons: $this->resolveObsoleteReasons($current, $predecessor, $state),
            historyChain: $this->buildHistoryChain($predecessor),
        );
    }

    private function buildUntouchedState(): DeclarationLifecycleStateData
    {
        return new DeclarationLifecycleStateData(
            state: DeclarationLifecycleState::Untouched,
            currentDeclaration: null,
            predecessorDeclaration: null,
            pendingClustersCount: 0,
            canGenerate: false,
            obsoleteReasons: [],
            historyChain: [],
        );
    }

    private function deriveState(
        FiscalDeclaration $current,
        bool $hasPredecessor,
        int $pendingClustersCount,
    ): DeclarationLifecycleState {
        if ($current->status === FiscalDeclarationStatus::Draft) {
            if ($hasPredecessor) {
                return DeclarationLifecycleState::RegenerationInProgress;
            }

            return $pendingClustersCount > 0
                ? DeclarationLifecycleState::DraftPending
                : DeclarationLifecycleState::DraftReadyToGenerate;
        }

        if ($current->status === FiscalDeclarationStatus::Deferred) {
            // Distinguish an initial deferral (no predecessor) from a
            // deferred regeneration (obsolete predecessor). The second
            // case must remind the user that an obsolete declaration is
            // still awaiting its new version.
            return $hasPredecessor
                ? DeclarationLifecycleState::DeferredRegeneration
                : DeclarationLifecycleState::Deferred;
        }

        return $current->is_obsolete
            ? DeclarationLifecycleState::GeneratedObsoleteOrphan
            : DeclarationLifecycleState::GeneratedActive;
    }

    /**
     * A newly detected cluster is "pending" iff no persisted decision
     * exists for its fingerprint (ADR-0015 § 6.5). Regeneration reuses
     * decisions for unchanged fingerprints automatically.
     */
    private function countPendingClusters(int $companyId, int $year): int
    {
        $clusters = $this->riskDetection->detectClusters($companyId, $year);
        if ($clusters === []) {
            return 0;
        }

        $decidedFingerprints = $this->decisions
            ->findAllForCompanyYear($companyId, $year)
            ->pluck('cluster_fingerprint')
            ->all();

        $decidedSet = array_flip($decidedFingerprints);

        $pending = 0;
        foreach ($clusters as $cluster) {
            if (! isset($decidedSet[$cluster->fingerprint])) {
                $pending++;
            }
        }

        return $pending;
    }

    /**
     * Obsolescence reasons for display ·
     *   - S6 (Generated obsolète orphan) · reasons live on `$current`.
     *   - S7 (Regeneration in progress) · reasons live on `$predecessor`
     *     (the obsolete Generated version superseded by the Draft).
     *   - Other states · no reasons.
     *
     * Defensive against a corrupted `obsolete_reasons` JSON (cast
     * returns a non-array or items that are not `array<string, mixed>`).
     * `InvalidationReasonData::fromArray()` would throw; the helper
     * `listFromRaw()` swallows the error, returns an empty fallback and
     * logs a warning so the UI degrades gracefully on corrupted data.
     *
     * @return list<InvalidationReasonData>
     */
    private function resolveObsoleteReasons(
        FiscalDeclaration $current,
        ?FiscalDeclaration $predecessor,
        DeclarationLifecycleState $state,
    ): array {
        $source = match ($state) {
            DeclarationLifecycleState::GeneratedObsoleteOrphan => $current,
            DeclarationLifecycleState::RegenerationInProgress,
            DeclarationLifecycleState::DeferredRegeneration => $predecessor,
            default => null,
        };

        if ($source === null) {
            return [];
        }

        return InvalidationReasonData::listFromRaw($source->obsolete_reasons, $source->id);
    }

    /**
     * History chain walked recursively via `findPredecessorOf` ·
     * `[predecessor, predecessor_of_predecessor, …]`. The current
     * declaration is excluded on purpose (already exposed as
     * `currentDeclaration`).
     *
     * Cycle protection · if a corrupted chain (A → B → A) loops, we
     * stop at the re-visit, log a warning on the `declarations` channel
     * and return the chain truncated at the cycle (resilience trumps
     * exception). No known trigger in V1; defensive against future
     * application bugs or direct DB manipulation.
     *
     * @return list<DeclarationListItemData>
     */
    private function buildHistoryChain(?FiscalDeclaration $startFrom): array
    {
        if ($startFrom === null) {
            return [];
        }

        $chain = [];
        $visited = [];
        $cursor = $startFrom;
        while ($cursor !== null) {
            if (isset($visited[$cursor->id])) {
                Log::channel('declarations')->warning('FiscalDeclaration.history_chain_cycle_detected', [
                    'start_declaration_id' => $startFrom->id,
                    'cycle_at_id' => $cursor->id,
                    'visited_ids' => array_keys($visited),
                ]);

                break;
            }

            $visited[$cursor->id] = true;
            $chain[] = DeclarationListItemData::fromModel($cursor);
            $cursor = $this->declarations->findPredecessorOf($cursor->id);
        }

        return $chain;
    }
}
