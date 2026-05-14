<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

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
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-013';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Catégorisation polluants';
    }

    public function description(): string
    {
        return 'Classement du véhicule dans les catégories E / 1 / « les plus polluants » selon motorisation et norme Euro.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        return 13;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-134',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
        ];
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Aiguillage,
            title: 'Étape 3 : quelle catégorie polluants ?',
            pitch: 'Trois catégories : E (électrique/hydrogène), 1 (essence ou gaz Euro 5/6), « plus polluants » (tous les autres, dont Diesel).',
            body: 'Catégorie E = électrique exclusif, hydrogène exclusif, ou combinaison des deux. Catégorie 1 = véhicules à allumage commandé (essence, GPL, GNV, E85, ou hybride essence) respectant Euro 5 ou Euro 6. Catégorie « véhicules les plus polluants » = tous les autres, notamment tous les Diesel (même Euro 6), les essence pré-Euro 5, et les véhicules sans norme Euro renseignée.',
            example: 'Tesla Model 3 électrique → E. Peugeot 308 essence Euro 6 → 1. Renault Trafic Diesel Euro 6 → plus polluants.',
        );
    }
}
