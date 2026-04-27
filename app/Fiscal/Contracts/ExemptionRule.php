<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * Règle d'exonération : évalue une condition (handicap, électrique,
 * LCD…) sur le contexte courant et retourne un verdict. Le pipeline
 * agrège tous les verdicts et applique le scope final (Both court-
 * circuit, Co2Only/PollutantsOnly atténue par taxe).
 */
interface ExemptionRule extends FiscalRule
{
    public function evaluate(PipelineContext $context): ExemptionVerdict;
}
