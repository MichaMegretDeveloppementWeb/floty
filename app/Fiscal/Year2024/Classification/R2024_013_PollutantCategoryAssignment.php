<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * R-2024-013 - Catégorisation polluants algorithmique
 * (CIBS art. L. 421-134).
 *
 * La cascade complète vit dans {@see PollutantCategory::derive()} pour
 * que la même logique s'applique aussi à l'écriture (Repository) et au
 * front (mirroir TS) - voir le docblock de l'enum.
 *
 * Cette règle pose simplement la catégorie résolue sur le contexte de
 * calcul, à partir des champs canoniques de la VFC (sans relire le
 * champ stocké `pollutant_category`, qui est lui-même posé par le
 * Repository selon la même cascade).
 */
final readonly class R2024_013_PollutantCategoryAssignment implements ClassificationRule
{
    public function ruleCode(): string
    {
        return 'R-2024-013';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Pollutants];
    }

    public function classify(PipelineContext $context): PipelineContext
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return $context;
        }

        $category = PollutantCategory::derive(
            $fiscal->energy_source,
            $fiscal->euro_standard,
            $fiscal->underlying_combustion_engine_type,
        );

        return $context
            ->withResolvedPollutantCategory($category)
            ->withAppliedRule($this->ruleCode());
    }
}
