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

/**
 * Compose l'état complet du cycle de vie d'une déclaration fiscale pour
 * un couple `(company, year)` (Phase 11 D5.8, Proposition IV).
 *
 * Centralise la dérivation de l'enum {@see DeclarationLifecycleState}
 * (S1 vierge ... S7 régénération en cours) depuis la combinaison
 * `status × is_obsolete × supersededBy × predecessor`. La
 * composition est consommée côté Inertia par `CompanyFiscalTab` via le
 * composant adaptatif `<DeclarationStateCard>`.
 *
 * Source unique de vérité pour la fiche Company : remplace le legacy
 * `fiscalActiveDeclaration` qui filtrait `is_obsolete = false` et
 * masquait les déclarations obsolètes orphelines.
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
        $current = $this->declarations->findCurrentForCompanyYear($companyId, $year);

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
            // Phase 13 D5.10.H · distinction sémantique entre une mise
            // de côté initiale (pas de predecessor) et une mise de côté
            // de régénération (predecessor obsolète). Les 2 réclament
            // une UX différente · le second cas doit rappeler à
            // l'utilisateur qu'une déclaration obsolète attend toujours
            // sa nouvelle version.
            return $hasPredecessor
                ? DeclarationLifecycleState::DeferredRegeneration
                : DeclarationLifecycleState::Deferred;
        }

        // FiscalDeclarationStatus::Generated
        return $current->is_obsolete
            ? DeclarationLifecycleState::GeneratedObsoleteOrphan
            : DeclarationLifecycleState::GeneratedActive;
    }

    /**
     * Décisions reprises : un cluster fraîchement détecté est « pending »
     * ssi aucune décision persistée n'existe pour son fingerprint (R-D3,
     * ADR-0015 § 6.5). La régénération réinjecte automatiquement les
     * décisions des fingerprints inchangés.
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
     * Motifs d'obsolescence pour l'affichage :
     *   - S6 (Generated obsolète orphan) : motifs portés par `$current`.
     *   - S7 (Régénération en cours) : motifs portés par `$predecessor`
     *     (la version Generated obsolète remplacée par le Draft courant).
     *   - Autres états : aucun motif à afficher.
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

        if ($source === null || $source->obsolete_reasons === null) {
            return [];
        }

        return array_map(
            static fn (array $raw): InvalidationReasonData => InvalidationReasonData::fromArray($raw),
            $source->obsolete_reasons,
        );
    }

    /**
     * Chaîne historique parcourue récursivement via `findPredecessorOf` :
     * `[predecessor, predecessor_of_predecessor, ...]`. La déclaration
     * courante est délibérément exclue (déjà exposée comme champ
     * dédié `currentDeclaration`).
     *
     * @return list<DeclarationListItemData>
     */
    private function buildHistoryChain(?FiscalDeclaration $startFrom): array
    {
        if ($startFrom === null) {
            return [];
        }

        $chain = [];
        $cursor = $startFrom;
        while ($cursor !== null) {
            $chain[] = DeclarationListItemData::fromModel($cursor);
            $cursor = $this->declarations->findPredecessorOf($cursor->id);
        }

        return $chain;
    }
}
