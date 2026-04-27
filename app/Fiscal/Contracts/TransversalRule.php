<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Règle transverse : prorata journalier, arrondi final, prise en
 * compte des indisponibilités, etc. Appliquée à la fin du pipeline
 * pour transformer les tarifs annuels pleins en montants finaux dus.
 */
interface TransversalRule extends FiscalRule
{
    public function apply(PipelineContext $context): PipelineContext;
}
