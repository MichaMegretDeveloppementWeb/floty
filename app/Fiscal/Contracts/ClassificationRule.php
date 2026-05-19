<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Classification rule: qualifies a vehicle characteristic (CO₂ method,
 * pollutant category, M1/N1 fiscal type) from the vehicle's fiscal
 * properties. The result is attached to the context via a dedicated
 * `with*` method; the rule never mutates the input context.
 */
interface ClassificationRule extends FiscalRule
{
    public function classify(PipelineContext $context): PipelineContext;
}
