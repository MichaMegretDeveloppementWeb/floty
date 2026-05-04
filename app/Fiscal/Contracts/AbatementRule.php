<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Règle d'abattement : modifie une caractéristique d'entrée AVANT que
 * la tarification soit calculée (ex. : abattement E85 réduit le CO₂
 * pris en compte par le barème). Vide en 2024 - sera utilisé en
 * 2025+.
 */
interface AbatementRule extends FiscalRule
{
    public function abate(PipelineContext $context): PipelineContext;
}
