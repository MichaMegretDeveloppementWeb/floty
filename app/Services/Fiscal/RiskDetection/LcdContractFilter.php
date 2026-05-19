<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Fiscal\Contracts\LcdQualifier;
use App\Models\Contract;

/**
 * Thin filter delegating LCD qualification to the registered
 * {@see LcdQualifier} rule (default · `R2024_021_ShortTermRental`).
 *
 * Authority lives in the rule (BOFiP § 180-190 · ≤ 30 days or whole
 * civil month), never in the persisted `contract_type`, which is
 * indicative only. Kept separate from the risk detection engine to ease
 * mocking in tests.
 */
final readonly class LcdContractFilter
{
    public function __construct(
        private LcdQualifier $rule,
    ) {}

    /**
     * True iff the contract qualifies as LCD per fiscal rules.
     */
    public function isLcd(Contract $contract): bool
    {
        return $this->rule->isShortTermRental($contract);
    }
}
