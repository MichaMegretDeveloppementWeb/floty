<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Pricing;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\FlatBracketRow;
use App\Fiscal\ValueObjects\FlatBracketsTable;
use App\Fiscal\ValueObjects\PollutantTariff;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Fiscal\Year2024\Classification\R2024_013_PollutantCategoryAssignment;

/**
 * R-2024-014 · flat annual pollutants tariff (CIBS L. 421-135).
 *
 *   - E (electric / hydrogen)             →   0 €
 *   - Category 1 (petrol/gas Euro 5/6)    → 100 €
 *   - Most polluting                      → 500 €
 *
 * The category itself is derived from the vehicle's fiscal
 * characteristics (`pollutant_category`, set on the context by
 * {@see R2024_013_PollutantCategoryAssignment}).
 */
final readonly class R2024_014_PollutantsFlat implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private PollutantTariff $tariff;

    public function __construct()
    {
        $this->tariff = new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => 100.0,
            PollutantCategory::MostPolluting->value => 500.0,
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-014';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Tarif forfaitaire polluants 2024';
    }

    public function description(): string
    {
        return "Tarif annuel forfaitaire par catégorie d'émissions (E = 0 € / 1 = 100 € / plus polluants = 500 €).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 14;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-135',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844528/2024-06-01',
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

    public function price(PipelineContext $context): PipelineContext
    {
        $category = $context->resolvedPollutantCategory;
        if ($category === null) {
            return $context;
        }

        return $context
            ->withPollutantsFullYearTariff($this->tariff->tariffFor($category))
            ->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Barème polluants : tarif forfaitaire par catégorie',
            pitch: 'Tarif annuel forfaitaire selon la catégorie polluants du véhicule (déterminée à l’étape 3 ci-dessus).',
            body: 'Le tarif annuel plein ne dépend ni de l’émission réelle ni de la cylindrée : c’est un forfait par catégorie. Il est multiplié par le prorata jours utilisés / 366.',
            flatBrackets: new FlatBracketsTable(
                header: ['Catégorie polluants', 'Tarif annuel 2024'],
                rows: [
                    new FlatBracketRow(
                        category: 'E · électrique / hydrogène',
                        amount: '0 €',
                        note: 'Effet du barème, pas une exonération.',
                    ),
                    new FlatBracketRow(category: '1 · essence/gaz Euro 5 ou 6', amount: '100 €'),
                    new FlatBracketRow(
                        category: 'Véhicules les plus polluants',
                        amount: '500 €',
                        note: 'Inclut tous les Diesel.',
                    ),
                ],
            ),
            example: 'Peugeot 308 essence Euro 6 (catégorie 1), utilisée 306/366 jours par ACME : 100 × 306/366 = 83,61 €. Renault Trafic Diesel Euro 6 (plus polluants), 366/366 jours : 500,00 €.',
        );
    }
}
