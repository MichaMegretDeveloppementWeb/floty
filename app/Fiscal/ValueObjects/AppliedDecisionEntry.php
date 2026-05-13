<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;

/**
 * Trace d'une décision de revue persistée matchée par fingerprint sur
 * un cluster fraîchement re-détecté lors du calcul de la déclaration
 * (Phase 11 D5.2).
 *
 * Les décisions `Conserved` figurent dans la liste à des fins d'audit
 * (le PDF affichera « Acceptée ») mais n'ont aucun effet sur le calcul
 * fiscal. Seules les `Requalified` alimentent
 * {@see FiscalDeclarationSnapshot::$optOutContractIds} et impactent les
 * montants.
 */
final readonly class AppliedDecisionEntry
{
    /**
     * @param  list<int>  $contractIds  IDs des contrats membres du cluster (source du fingerprint)
     * @param  list<int>  $excludedContractIds  Phase 13 D5.10.S · IDs des contrats explicitement exclus du cluster par l'utilisateur
     */
    public function __construct(
        public string $clusterFingerprint,
        public RiskCode $riskCode,
        public ReviewDecisionType $decision,
        public array $contractIds,
        public ?string $justification,
        public array $excludedContractIds = [],
    ) {}
}
