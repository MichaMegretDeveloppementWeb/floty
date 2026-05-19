<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;

/**
 * "Contract" row of a {@see FiscalDeclarationSnapshot}. The declaration
 * is broken down per contract (chronological view), not per vehicle
 * (aggregated opaque view).
 *
 * Why per contract: a vehicle used 91 days for Y € of tax tells nothing
 * about temporal repartition or motives (EV motorisation, LCD opt-out,
 * LCD cumul). The per-contract view lets the user (and tax
 * administration in audit) understand each line, and materialises
 * risky LCD clusters visually (contracts of a cluster share the same
 * `clusterFingerprint`).
 *
 * Sort order: entries are sorted by `(vehicleId, startDate)` for a
 * natural visual grouping on the frontend. Consecutive contracts of
 * the same LCD cluster are adjacent so the `<ClusterGroup>` component
 * can wrap them in a single visual box.
 *
 * Amounts: `co2Due`, `pollutantsDue`, `totalDue` are rounded to the
 * cent (HALF_UP). Their sum across all entries equals
 * {@see FiscalDeclarationSnapshot::$totalDue} up to rounding
 * (R-2024-003 single rounding per taxpayer invariant).
 *
 * Proportional split: per-contract tax is
 * `(days_in_contract_year / days_in_pair_year) × tax_pair`, matching
 * the R-2024-002 daily prorata. If every contract of a pair is
 * LCD-exempt, tax = 0 €.
 *
 * Vehicle fiscal characteristics: `vehicleFiscalSummary` is
 * pre-formatted (e.g. `M1 · WLTP 100 g · Euro 6`), more informative
 * than a plain label and useful in audit.
 */
final readonly class ContractSnapshotEntry
{
    public function __construct(
        public int $contractId,
        public ?string $contractReference,
        public ContractType $contractType,
        /** ISO 8601 `Y-m-d`. */
        public string $startDate,
        /** ISO 8601 `Y-m-d`. */
        public string $endDate,
        /** Days of the contract **inside the target year** (may be < total duration if straddling). */
        public int $daysInYearAssigned,
        public int $vehicleId,
        public string $vehicleLabel,
        public string $vehicleFiscalSummary,
        public float $co2Due,
        public float $pollutantsDue,
        public float $totalDue,
        public ?string $clusterFingerprint,
        public ?RiskCode $clusterRiskCode,
        public ?RiskLevel $clusterRiskLevel,
        public ?ReviewDecisionType $clusterDecision,
        public ?string $clusterJustification,
        /**
         * ID of the predecessor declaration from which the decision was
         * automatically retained by fingerprint matching. Null if:
         *  - no decision exists for this cluster, OR
         *  - the decision was taken during the current review session.
         *
         * Lets the frontend `<ClusterGroup>` show a retained-decision
         * badge distinguishing inherited decisions from those taken in
         * the current session.
         */
        public ?int $clusterDecisionRetainedFrom,
        public bool $isOptedOut,
        /**
         * User-facing exemption reason for this contract (PDF + UI).
         * Null if the contract is taxed. Format: `« Exonéré R-XXXX-YYY
         * · {short name} (CIBS L. {article}) »`. Computed by
         * {@see DeclarationFiscalEngine}
         * from the contract's fiscal qualification (individual non
         * opt-out LCD → R-2024-021; other cases to come).
         */
        public ?string $exemptionReason,
    ) {}
}
