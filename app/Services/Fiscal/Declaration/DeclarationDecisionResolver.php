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
 * Résolveur des décisions humaines de revue fiscale (Lot 4 D11 ·
 * F-19-004) extrait de `DeclarationFiscalEngine` pour respecter SRP.
 *
 * Concentre les 3 responsabilités de matching cluster ↔ décision ·
 *   - {@see buildAppliedDecisionsAndOptOuts} · transforme les
 *     décisions BDD en `AppliedDecisionEntry` + extrait les contractIds
 *     opt-out (Requalified non-exclus) pour le décorateur LCD runtime.
 *   - {@see resolveRetainedFromMap} · identifie les décisions
 *     « reprises auto par fingerprint » du prédécesseur d'une
 *     régénération (badge 🔁 frontend).
 *   - {@see buildContractClusterMap} · construit la map
 *     `contractId => clusterInfo` pour enrichir chaque
 *     `ContractSnapshotEntry` avec son cluster + décision +
 *     `retainedFrom`.
 *
 * **Pure logique de matching** · aucun calcul fiscal, aucun appel
 * pipeline. Le seul side-effect est la lecture des déclarations
 * BDD pour résoudre la chaîne de prédécesseurs.
 */
final readonly class DeclarationDecisionResolver
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    /**
     * Match decisions ↔ clusters par fingerprint. Pattern aligné sur
     * {@see App\Services\Fiscal\RiskDetection\DeclarationPreviewService::applyPersistedDecisions}.
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

            // Phase 13 D5.10.S · contrats explicitement exclus du
            // cluster par l'utilisateur · ils sont traités comme LCD
            // individuels exemptés R-2024-021 et ne participent pas à
            // l'opt-out même si la décision globale est Requalified.
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
     * Résout les décisions « reprises auto par fingerprint » du
     * prédécesseur (Phase 11 D5.8.2 amélioration B audit).
     *
     * Une décision est dite « reprise » si la déclaration courante du
     * couple `(company, year)` a un prédécesseur (= régénération en
     * cours ou achevée) ET que la décision matchée par fingerprint a
     * été persistée **avant** la création de la déclaration courante
     * (`decided_at < currentDeclaration.created_at`).
     *
     * Retourne une map `fingerprint => predecessorDeclarationId` qui
     * permet au frontend d'afficher le badge `🔁 Décision reprise`.
     * Map vide si la déclaration courante n'est pas une régénération
     * (= première préparation) ou si aucune décision n'a été
     * persistée avant la session courante.
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
     * Construit la map `contractId => clusterInfo` pour enrichir
     * chaque {@see App\Fiscal\ValueObjects\ContractSnapshotEntry} avec
     * son cluster d'appartenance + décision persistée si présente +
     * flag `retainedFrom` (D5.8.2 amélioration B audit).
     *
     * @param  list<ReviewClusterData>  $clusters
     * @param  list<AppliedDecisionEntry>  $appliedDecisions
     * @param  array<string, int>  $retainedFromByFingerprint  Map fingerprint → id de la déclaration prédécesseur d'où la décision a été reprise
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
                // Phase 13 D5.10.S · les contrats explicitement exclus
                // par l'utilisateur sortent du cluster côté snapshot ·
                // pas de mapping = `clusterFingerprint=null` dans
                // `ContractSnapshotEntry`, rendu comme une row simple
                // hors du bloc cluster côté frontend.
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
