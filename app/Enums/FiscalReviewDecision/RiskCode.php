<?php

declare(strict_types=1);

namespace App\Enums\FiscalReviewDecision;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * LCD fiscal risk codes (ADR-0015 § 3.2).
 *
 * - `R-LCD-CHAIN` (medium): chain of ≥2 LCD contracts with intervals ≤ `max_interval` days and cumul > `threshold_low`.
 * - `R-LCD-CHAIN-FORT` (high): chain with cumul > `threshold_high` or ≥ `count_high` contracts.
 */
#[TypeScript]
enum RiskCode: string
{
    case Chain = 'R-LCD-CHAIN';
    case ChainFort = 'R-LCD-CHAIN-FORT';

    /**
     * Deterministic mapping to the associated risk level.
     */
    public function level(): RiskLevel
    {
        return match ($this) {
            self::Chain => RiskLevel::Moyen,
            self::ChainFort => RiskLevel::Eleve,
        };
    }
}
