<?php

declare(strict_types=1);

namespace App\Enums\FiscalReviewDecision;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Code de la grille V1 des risques fiscaux LCD (ADR-0015 § 3.2).
 *
 *  - `R-LCD-CHAIN` (niveau moyen) : chaîne de ≥2 contrats LCD avec
 *    intervalles ≤ `max_interval` jours et cumul > `threshold_low`.
 *  - `R-LCD-CHAIN-FORT` (niveau élevé) : chaîne avec cumul >
 *    `threshold_high` ou ≥ `count_high` contrats.
 *
 * Note ADR-0015 § 3.2 : `R-LCD-DRIVER` (chaîne avec même conducteur)
 * est reporté en V1.x, le temps que la méthode multi-conducteur soit
 * stabilisée avec Renaud.
 */
#[TypeScript]
enum RiskCode: string
{
    case Chain = 'R-LCD-CHAIN';
    case ChainFort = 'R-LCD-CHAIN-FORT';

    /**
     * Niveau de risque associé au code, mappage déterministe.
     */
    public function level(): RiskLevel
    {
        return match ($this) {
            self::Chain => RiskLevel::Moyen,
            self::ChainFort => RiskLevel::Eleve,
        };
    }
}
