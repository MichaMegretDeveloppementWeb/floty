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
 * R-2024-010 - Barème CO₂ WLTP 2024 (CIBS art. L. 421-120).
 *
 * Tarif progressif par tranches à tarif marginal sur les émissions
 * CO₂ WLTP (g/km). 9 tranches, dernière ouverte (≥ 175 g/km).
 *
 * S'exécute uniquement si la méthode CO₂ résolue par
 * {@see R2024_005_Co2MethodSelection} est WLTP. Sinon no-op.
 */
final readonly class R2024_010_WltpProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 14, 0.0),
            new BracketRange(14, 55, 1.0),
            new BracketRange(55, 63, 2.0),
            new BracketRange(63, 95, 3.0),
            new BracketRange(95, 115, 4.0),
            new BracketRange(115, 135, 10.0),
            new BracketRange(135, 155, 50.0),
            new BracketRange(155, 175, 60.0),
            new BracketRange(175, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-010';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Barème WLTP 2024';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ WLTP.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 10;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-120',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844602/2024-06-01',
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
        if ($context->resolvedCo2Method !== HomologationMethod::Wltp) {
            return $context;
        }
        $co2 = $context->currentFiscalCharacteristics->co2_wltp ?? 0;
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
            title: 'Barème CO₂ WLTP (véhicules récents)',
            pitch: 'Tarif progressif par tranches calculé sur les grammes de CO₂ par km (valeur WLTP).',
            body: "Pour chaque tranche traversée par l'émission du véhicule, on multiplie la fraction d'émission tombant dans la tranche par le tarif marginal de cette tranche. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 366).",
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'g CO₂/km',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu’à 14', rate: '0 €/g'),
                    new ProgressiveBracketRow(label: 'De 15 à 55', rate: '1 €/g'),
                    new ProgressiveBracketRow(label: 'De 56 à 63', rate: '2 €/g'),
                    new ProgressiveBracketRow(label: 'De 64 à 95', rate: '3 €/g'),
                    new ProgressiveBracketRow(label: 'De 96 à 115', rate: '4 €/g'),
                    new ProgressiveBracketRow(label: 'De 116 à 135', rate: '10 €/g'),
                    new ProgressiveBracketRow(label: 'De 136 à 155', rate: '50 €/g'),
                    new ProgressiveBracketRow(label: 'De 156 à 175', rate: '60 €/g'),
                    new ProgressiveBracketRow(label: 'À partir de 176', rate: '65 €/g'),
                ],
            ),
            example: 'Peugeot 308 WLTP 100 g/km, utilisée 306 jours par ACME en 2024 : tarif plein = 14×0 + 41×1 + 8×2 + 32×3 + 5×4 = 173 €. Taxe due = 173 × 306/366 = 144,64 €.',
        );
    }
}
