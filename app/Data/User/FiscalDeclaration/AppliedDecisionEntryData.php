<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation frontend d'une décision de revue appliquée au calcul
 * (Phase 11 D5.6). Miroir DTO du VO domaine
 * {@see AppliedDecisionEntry}.
 *
 * Consommé par {@see resources/js/Components/Domain/Declaration/ClustersHistoryList.vue}.
 */
#[TypeScript]
final class AppliedDecisionEntryData extends Data
{
    /**
     * @param  list<int>  $contractIds
     * @param  list<int>  $excludedContractIds  Phase 13 D5.10.S · contrats exclus du cluster par l'utilisateur
     */
    public function __construct(
        public string $clusterFingerprint,
        public RiskCode $riskCode,
        public ReviewDecisionType $decision,
        public array $contractIds,
        public ?string $justification,
        public array $excludedContractIds = [],
    ) {}

    public static function fromValueObject(AppliedDecisionEntry $vo): self
    {
        return new self(
            clusterFingerprint: $vo->clusterFingerprint,
            riskCode: $vo->riskCode,
            decision: $vo->decision,
            contractIds: $vo->contractIds,
            justification: $vo->justification,
            excludedContractIds: $vo->excludedContractIds,
        );
    }
}
