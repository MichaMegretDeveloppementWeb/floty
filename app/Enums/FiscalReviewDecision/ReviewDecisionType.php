<?php

declare(strict_types=1);

namespace App\Enums\FiscalReviewDecision;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Décision humaine prise sur un cluster de risque LCD (ADR-0015 § D3).
 *
 *  - `conserved` : l'utilisateur conserve l'exonération LCD : la
 *    lecture stricte par contrat reste appliquée.
 *  - `requalified` : l'utilisateur requalifie les contrats du cluster
 *    en LLD (ils perdent leur exonération individuellement et
 *    deviennent taxables au prorata sur leur véhicule).
 */
#[TypeScript]
enum ReviewDecisionType: string
{
    case Conserved = 'conserved';
    case Requalified = 'requalified';
}
