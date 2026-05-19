<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Abatement rule: alters an input characteristic before the pricing
 * stage (e.g. E85 abatement on the CO₂ value used by the bracket).
 * Empty in 2024, used from 2025 onwards.
 */
interface AbatementRule extends FiscalRule
{
    public function abate(PipelineContext $context): PipelineContext;
}
