<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Pricing rule: computes a full-year tariff (CO₂ or pollutants) and
 * writes it onto the context. Multiple pricing rules can run in the
 * pipeline (typically mutually exclusive WLTP/NEDC/PA brackets plus a
 * flat pollutants tariff).
 */
interface PricingRule extends FiscalRule
{
    public function price(PipelineContext $context): PipelineContext;
}
