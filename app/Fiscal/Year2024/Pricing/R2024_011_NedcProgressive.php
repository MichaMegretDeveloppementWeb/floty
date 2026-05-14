<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Pricing;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\BracketRange;
use App\Fiscal\ValueObjects\ProgressiveBracketRow;
use App\Fiscal\ValueObjects\ProgressiveBracketsTable;
use App\Fiscal\ValueObjects\ProgressiveScale;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-011 - Barème CO₂ NEDC 2024 (CIBS art. L. 421-121).
 *
 * Barème progressif sur les émissions CO₂ NEDC (g/km). Concerne les
 * véhicules antérieurs à WLTP. 9 tranches, dernière ouverte
 * (≥ 145 g/km). S'exécute uniquement si la méthode CO₂ résolue est
 * NEDC.
 */
final readonly class R2024_011_NedcProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 12, 0.0),
            new BracketRange(12, 45, 1.0),
            new BracketRange(45, 52, 2.0),
            new BracketRange(52, 79, 3.0),
            new BracketRange(79, 95, 4.0),
            new BracketRange(95, 112, 10.0),
            new BracketRange(112, 128, 50.0),
            new BracketRange(128, 145, 60.0),
            new BracketRange(145, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-011';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Barème NEDC 2024';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ NEDC (véhicules antérieurs à WLTP).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 11;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-121',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844592/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function price(PipelineContext $context): PipelineContext
    {
        if ($context->resolvedCo2Method !== HomologationMethod::Nedc) {
            return $context;
        }
        $co2 = $context->currentFiscalCharacteristics->co2_nedc ?? 0;
        $tariff = $this->scale->apply($co2);

        return $context
            ->withCo2FullYearTariff($tariff)
            ->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Barème CO₂ NEDC (véhicules 2004-2020)',
            pitch: 'Même mécanique que WLTP, mais avec des seuils plus bas car la norme NEDC mesure des émissions plus optimistes.',
            body: 'Tarif progressif à tarif marginal identique dans sa logique au barème WLTP. Les tranches sont décalées vers le bas : à tarif équivalent, on atteint une tranche donnée avec moins de grammes mesurés en NEDC qu’en WLTP.',
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'g CO₂/km',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu’à 12', rate: '0 €/g'),
                    new ProgressiveBracketRow(label: 'De 13 à 45', rate: '1 €/g'),
                    new ProgressiveBracketRow(label: 'De 46 à 52', rate: '2 €/g'),
                    new ProgressiveBracketRow(label: 'De 53 à 79', rate: '3 €/g'),
                    new ProgressiveBracketRow(label: 'De 80 à 95', rate: '4 €/g'),
                    new ProgressiveBracketRow(label: 'De 96 à 112', rate: '10 €/g'),
                    new ProgressiveBracketRow(label: 'De 113 à 128', rate: '50 €/g'),
                    new ProgressiveBracketRow(label: 'De 129 à 145', rate: '60 €/g'),
                    new ProgressiveBracketRow(label: 'À partir de 146', rate: '65 €/g'),
                ],
            ),
            example: 'Peugeot 207 essence NEDC 130 g/km, utilisée 366/366 jours par B : tarif plein = 33+14+81+64+170+800+120 = 1 282 €. Taxe due = 1 282,00 €.',
        );
    }
}
