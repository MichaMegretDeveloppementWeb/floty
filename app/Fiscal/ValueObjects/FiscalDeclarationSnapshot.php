<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Immutable image of the fiscal calculation of a declaration for a
 * `(company, year)` pair.
 *
 * Declaration calculation is not a plain fiscal calculation: it also
 * applies human review decisions ("Requalified" on some LCD clusters).
 * The snapshot captures the result exactly as computed at generation
 * time, with traces of the applied decisions and the effective opt-out
 * contracts.
 *
 * Per-contract breakdown sorted chronologically (rather than
 * per-vehicle) lets the user understand each row (date, duration,
 * exemption motive) and materialises risky LCD chains directly in the
 * list via the shared `clusterFingerprint`. See `ContractSnapshotEntry`.
 *
 * Strict immutability: `final readonly` + promoted properties +
 * `final readonly` sub-VOs. No mutation possible after construction.
 *
 * Persistence: serialised via `FiscalDeclarationSnapshotData` at
 * `markAsGenerated()` time to guarantee fiscal immutability of the
 * historical PDF vs on-the-fly recomputation.
 */
final readonly class FiscalDeclarationSnapshot
{
    /**
     * @param  list<ContractSnapshotEntry>  $contractBreakdown  Per-contract detail, sorted by (vehicleId, startDate)
     * @param  list<AppliedDecisionEntry>  $appliedDecisions  Persisted review decisions matched on re-detected clusters
     * @param  list<int>  $optOutContractIds  IDs of contracts effectively requalified
     */
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public CarbonImmutable $computedAt,
        public float $co2DueTotal,
        public float $pollutantsDueTotal,
        public float $totalDue,
        public array $contractBreakdown,
        public array $appliedDecisions,
        public array $optOutContractIds,
        /**
         * Formatted postal address of the user company, captured at
         * generation time to freeze the fiscal identity in the PDF.
         * Lines separated by `\n`. Null if no part of the address is
         * filled.
         */
        public ?string $companyAddress = null,
    ) {}
}
