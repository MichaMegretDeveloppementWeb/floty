<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Risk cluster detected by the engine (ADR-0015 § 4).
 *
 * Identified functionally by `fingerprint`: on regeneration, clusters
 * whose fingerprint is unchanged keep their decision. `decision` and
 * `justification` start as `null`; matching against persisted decisions
 * happens later in the pipeline.
 *
 * The qualifying criterion for an at-risk LCD chain is the continuous
 * coverage range (earliest start -> latest end, clipped to the fiscal
 * year), not the sum of individual `expandToDaysInYear`. This reflects
 * the R-LCD-CHAIN doctrine (CIBS L. 421-141): the risk lies in temporal
 * usage continuity, not in cumulative individual durations.
 */
#[TypeScript]
final class ReviewClusterData extends Data
{
    /**
     * @param  list<ClusterContractData>  $contracts
     */
    public function __construct(
        public RiskCode $code,
        public RiskLevel $level,
        public string $fingerprint,
        #[DataCollectionOf(ClusterContractData::class)]
        public array $contracts,
        public int $contractsCount,
        /** Coverage in days = `(coverageEndDate - coverageStartDate) + 1`, clipped to the fiscal year. */
        public int $coveragePeriodDays,
        /** ISO Y-m-d, effective start clipped to the year. */
        public string $coverageStartDate,
        /** ISO Y-m-d, effective end clipped to the year. */
        public string $coverageEndDate,
        /** Number of distinct vehicles touched by the chain. */
        public int $distinctVehiclesCount,
        public ?ReviewDecisionType $decision = null,
        public ?string $justification = null,
        /**
         * Contract ids explicitly opted out of the chain by the user.
         * These contracts are treated as individual LCD exempted under
         * R-2024-021 and do not participate in any Requalified opt-out.
         * `contractsCount`, `coveragePeriodDays`, coverage dates and
         * `distinctVehiclesCount` reflect the included contracts only
         * (contracts - exclusions). The `contracts` list stays raw so
         * the modal can toggle each contract individually.
         *
         * @var list<int>
         */
        public array $excludedContractIds = [],
    ) {}
}
