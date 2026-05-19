<?php

declare(strict_types=1);

namespace App\Enums\FiscalReviewDecision;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Risk level for a detected cluster (ADR-0015 § 3.2). Drives badge color
 * and validation: a written justification is required to conserve a high-risk cluster.
 */
#[TypeScript]
enum RiskLevel: string
{
    case Moyen = 'moyen';
    case Eleve = 'eleve';

    /**
     * Whether a written justification is required for `conserved` on this level (ADR-0015 § 6.2).
     */
    public function requiresJustificationOnConserved(): bool
    {
        return $this === self::Eleve;
    }
}
