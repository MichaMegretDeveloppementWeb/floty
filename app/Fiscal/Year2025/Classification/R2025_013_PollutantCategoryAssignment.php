<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Classification;

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
 * R-2025-013 · Catégorisation polluants algorithmique (CIBS art.
 * L. 421-134) · reconduction stricte R-2024-013. L'article L. 421-134
 * est dans une version stable inchangée 2024 → 2025.
 *
 * La cascade complète vit dans {@see PollutantCategory::derive()} pour
 * que la même logique s'applique au Repository (écriture) et au front
 * (mirror TS).
 */
final readonly class R2025_013_PollutantCategoryAssignment implements ClassificationRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-013';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Catégorisation polluants';
    }

    public function description(): string
    {
        return 'Classement du véhicule dans les catégories E / 1 / « les plus polluants » selon motorisation et norme Euro. Reconduction stricte 2024 (L. 421-134 inchangé).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2025-01-01',
                'consulted_at' => '2026-05-14',
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
            tab: RuleTab::Cadre,
            section: RuleSection::Aiguillage,
            title: 'Étape 3 : quelle catégorie polluants ?',
            pitch: 'Trois catégories : E (électrique/hydrogène), 1 (essence ou gaz Euro 5/6), « plus polluants » (tous les autres, dont Diesel).',
            body: 'Catégorie E = électrique exclusif, hydrogène exclusif, ou combinaison des deux. Catégorie 1 = véhicules à allumage commandé (essence, GPL, GNV, E85, ou hybride essence) respectant Euro 5 ou Euro 6. Catégorie « véhicules les plus polluants » = tous les autres, notamment tous les Diesel (même Euro 6), les essence pré-Euro 5, et les véhicules sans norme Euro renseignée. Reconduction stricte 2024.',
            example: 'Tesla Model 3 électrique → E. Peugeot 308 essence Euro 6 → 1. Renault Trafic Diesel Euro 6 → plus polluants.',
        );
    }
}
