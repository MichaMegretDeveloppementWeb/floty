<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use App\Models\Contract;
use App\Services\Fiscal\RiskDetection\LcdContractFilter;

/**
 * Contract for the fiscal LCD (short-term rental) qualifier as defined
 * by CIBS (ADR-0014 + BOFiP § 180-190).
 *
 * Rule R-2024-021 exposes this qualifier. Several services consume it
 * ({@see R2024_008_ReductiveUnavailability},
 * {@see LcdContractFilter}). The
 * interface lets a decorator substitute the rule at runtime (skip-rule
 * mechanism driven by human review decisions); consumers depend on this
 * interface rather than the concrete class.
 *
 * Known implementers:
 * - {@see R2024_021_ShortTermRental} ·
 *   canonical implementation, strict BOFiP reading per contract.
 * - {@see R2024_021_WithOptOuts} ·
 *   runtime decorator that forces `false` for contracts marked opt-out
 *   (Requalified review decisions).
 */
interface LcdQualifier
{
    /**
     * True iff the contract qualifies as LCD under CIBS.
     */
    public function isShortTermRental(Contract $contract): bool;
}
