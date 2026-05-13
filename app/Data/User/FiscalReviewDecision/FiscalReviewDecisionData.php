<?php

declare(strict_types=1);

namespace App\Data\User\FiscalReviewDecision;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\FiscalReviewDecision;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Décision humaine de revue fiscale pour un cluster de risque
 * (Phase 11 D1, ADR-0015 § 5.2). Identifiée fonctionnellement par
 * `(companyId, fiscalYear, clusterFingerprint)` côté BDD ; en lecture
 * on expose aussi le nom de l'auteur pour l'historique UI (D4).
 */
#[TypeScript]
final class FiscalReviewDecisionData extends Data
{
    /**
     * @param  list<int>  $excludedContractIds
     */
    public function __construct(
        public int $id,
        public int $companyId,
        public int $fiscalYear,
        public RiskCode $riskCode,
        public string $clusterFingerprint,
        public ReviewDecisionType $decision,
        public ?string $justification,
        /** Phase 13 D5.10.S · contractIds explicitement exclus du cluster par l'utilisateur. */
        public array $excludedContractIds,
        /** ISO 8601 (Y-m-d\TH:i:sP). */
        public string $decidedAt,
        public string $decidedByUserName,
    ) {}

    public static function fromModel(FiscalReviewDecision $decision): self
    {
        return new self(
            id: $decision->id,
            companyId: $decision->company_id,
            fiscalYear: $decision->fiscal_year,
            riskCode: $decision->risk_code,
            clusterFingerprint: $decision->cluster_fingerprint,
            decision: $decision->decision,
            justification: $decision->justification,
            excludedContractIds: $decision->excluded_contract_ids ?? [],
            decidedAt: $decision->decided_at->toIso8601String(),
            decidedByUserName: trim($decision->decidedBy->first_name.' '.$decision->decidedBy->last_name),
        );
    }
}
