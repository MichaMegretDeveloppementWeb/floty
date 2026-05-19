<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;

/**
 * Persisted review decision matched (by fingerprint) on a cluster
 * re-detected during the declaration calculation.
 *
 * `Conserved` decisions appear in the list for audit purposes (the PDF
 * displays "Acceptée") but have no effect on the calculation. Only
 * `Requalified` decisions feed
 * {@see FiscalDeclarationSnapshot::$optOutContractIds} and impact the
 * amounts.
 */
final readonly class AppliedDecisionEntry
{
    /**
     * @param  list<int>  $contractIds  IDs of the contracts inside the cluster (fingerprint source)
     * @param  list<int>  $excludedContractIds  IDs of contracts explicitly excluded from the cluster by the user
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
