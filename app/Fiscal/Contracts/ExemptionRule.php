<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * Exemption rule: evaluates a condition (handicap, electric, LCD…) on
 * the current context and returns a verdict. The pipeline aggregates
 * verdicts and applies the resulting scope (Both short-circuits both
 * taxes; Co2Only / PollutantsOnly zeroes a single tax).
 */
interface ExemptionRule extends FiscalRule
{
    public function evaluate(PipelineContext $context): ExemptionVerdict;
}
