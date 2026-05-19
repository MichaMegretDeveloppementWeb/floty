<?php

declare(strict_types=1);

namespace App\Enums\FiscalReviewDecision;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Human decision on an LCD risk cluster (ADR-0015 § D3).
 *
 * - `conserved`: keep the LCD exoneration; per-contract reading remains in force.
 * - `requalified`: reclassify cluster contracts to LLD; they become taxable on a prorata basis.
 */
#[TypeScript]
enum ReviewDecisionType: string
{
    case Conserved = 'conserved';
    case Requalified = 'requalified';
}
