<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Règle de tarification : calcule un tarif annuel plein (CO₂ ou
 * polluants) et le pose sur le contexte. Plusieurs règles Pricing
 * peuvent s'exécuter dans le pipeline (typiquement WLTP/NEDC/PA
 * exclusifs selon la classification CO₂ + un tarif polluants
 * forfaitaire).
 */
interface PricingRule extends FiscalRule
{
    public function price(PipelineContext $context): PipelineContext;
}
