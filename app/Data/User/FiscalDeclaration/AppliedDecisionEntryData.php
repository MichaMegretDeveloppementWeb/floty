<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Frontend representation of a review decision applied to a fiscal
 * computation. Mirror of the domain VO {@see AppliedDecisionEntry}.
 */
#[TypeScript]
final class AppliedDecisionEntryData extends Data
{
    /**
     * @param  list<int>  $contractIds
     * @param  list<int>  $excludedContractIds  Contracts the user opted out of the cluster.
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
