<?php

declare(strict_types=1);

namespace App\Enums\FiscalReviewDecision;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Niveau de risque associé à un cluster détecté (ADR-0015 § 3.2).
 *
 * Le niveau pilote l'UI (badge couleur) et la validation : une
 * justification écrite est obligatoire pour conserver l'exonération
 * LCD sur un cluster de niveau `Eleve`.
 */
#[TypeScript]
enum RiskLevel: string
{
    case Moyen = 'moyen';
    case Eleve = 'eleve';

    /**
     * Une justification écrite est requise pour décider
     * `conserved` sur un cluster de niveau élevé (ADR-0015 § 6.2).
     */
    public function requiresJustificationOnConserved(): bool
    {
        return $this === self::Eleve;
    }
}
