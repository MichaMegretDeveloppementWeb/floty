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
 * R-2024-012 · Administrative Horsepower bracket 2024 (CIBS L. 421-122).
 *
 * Progressive bracket on the administrative horsepower (CV). Historic
 * fallback for vehicles without CO₂ data. Five slices, the last one
 * open (> 15 CV). Runs only if the resolved CO₂ method is PA.
 */
final readonly class R2024_012_PaProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 3, 1500.0),
            new BracketRange(3, 6, 2250.0),
            new BracketRange(6, 10, 3750.0),
            new BracketRange(10, 15, 4750.0),
            new BracketRange(15, null, 6000.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-012';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Barème Puissance Administrative 2024';
    }

    public function description(): string
    {
        return 'Tarif forfaitaire sur la puissance fiscale (véhicules pré-2004 ou sans CO₂).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 12;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-122',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844579/2024-06-01',
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
        if ($context->resolvedCo2Method !== HomologationMethod::Pa) {
            return $context;
        }
        $cv = $context->currentFiscalCharacteristics->taxable_horsepower ?? 0;
        $tariff = $this->scale->apply($cv);

        return $context
            ->withCo2FullYearTariff($tariff)
            ->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Barème CO₂ Puissance Administrative (anciens véhicules)',
            pitch: 'Barème de repli quand la valeur CO₂ est indisponible : tarif progressif sur les chevaux fiscaux (CV).',
            body: "S'applique aux véhicules immatriculés avant le 01/06/2004, à ceux déjà affectés à des fins économiques avant 2006, et à tous les cas où la donnée CO₂ attendue est manquante. Même logique de tarif marginal par tranches, appliquée sur la puissance administrative.",
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'CV fiscaux',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu’à 3', rate: '1 500 €/CV'),
                    new ProgressiveBracketRow(label: 'De 4 à 6', rate: '2 250 €/CV'),
                    new ProgressiveBracketRow(label: 'De 7 à 10', rate: '3 750 €/CV'),
                    new ProgressiveBracketRow(label: 'De 11 à 15', rate: '4 750 €/CV'),
                    new ProgressiveBracketRow(label: 'À partir de 16', rate: '6 000 €/CV'),
                ],
            ),
            example: 'Renault 21 essence 7 CV, 1ère immat. 2002, utilisée 366/366 jours en 2024 : 3×1 500 + 3×2 250 + 1×3 750 = 15 000 €. Taxe due = 15 000,00 €.',
        );
    }
}
