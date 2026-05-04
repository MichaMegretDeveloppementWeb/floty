<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Règle de classification : produit une qualification (méthode CO₂,
 * catégorie polluants, type fiscal M1/N1) à partir des caractéristiques
 * véhicule. Le résultat est attaché au contexte via une méthode `with*`
 * dédiée - la règle ne mute jamais le contexte d'entrée.
 */
interface ClassificationRule extends FiscalRule
{
    public function classify(PipelineContext $context): PipelineContext;
}
