<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Pricing;

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
 * R-2025-012 - 2025 Puissance Administrative scale, MAJOR HARDENING
 * vs 2024 (CIBS art. L. 421-122 version 01/01/2025, modified by
 * LF 2024 art. 97, 19°).
 *
 * Progressive bracket-based tariff with marginal rate in €/CV.
 * 5 brackets, last one open (≥ 16 CV).
 *
 * Key differences vs R-2024-012:
 * - 1st bracket: 1 750 €/CV (vs 1 500 € in 2024).
 * - 10 CV = 29 750 € in 2025 (vs 26 250 € in 2024).
 *
 * Only runs if the resolved CO₂ method is PA (see R-2025-005 and
 * R-2025-006).
 *
 * Source: Légifrance LEGIARTI000048886433.
 */
final readonly class R2025_012_PaProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        // Hardened 2025 PA scale (LF 2024 art. 97, 19°).
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 3, 1750.0),
            new BracketRange(3, 6, 2500.0),
            new BracketRange(6, 10, 4250.0),
            new BracketRange(10, 15, 5000.0),
            new BracketRange(15, null, 6250.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2025-012';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Barème PA 2025';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur la puissance administrative (CV). Durcissement majeur au 01/01/2025 (LF 2024 art. 97, 19°). 10 CV = 29 750 €.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048886433/2025-01-01',
                'consulted_at' => '2026-05-13',
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
            title: 'Barème Puissance Administrative 2025 (durcissement majeur vs 2024)',
            pitch: 'Tarif progressif par tranches sur la puissance administrative (CV). Appliqué aux véhicules anciens ou sans données CO₂. Barème durci au 01/01/2025.',
            body: 'Pour chaque tranche traversée par la puissance administrative du véhicule, on multiplie la fraction de CV tombant dans la tranche par le tarif marginal. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 365). Le barème a été durci au 01/01/2025 par la LF 2024 art. 97, 19°.',
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'CV',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu\'à 3', rate: '1 750 €/CV'),
                    new ProgressiveBracketRow(label: 'De 4 à 6', rate: '2 500 €/CV'),
                    new ProgressiveBracketRow(label: 'De 7 à 10', rate: '4 250 €/CV'),
                    new ProgressiveBracketRow(label: 'De 11 à 15', rate: '5 000 €/CV'),
                    new ProgressiveBracketRow(label: 'À partir de 16', rate: '6 250 €/CV'),
                ],
            ),
            example: 'Véhicule 10 CV utilisé toute l\'année 2025 : tarif plein = 3×1 750 + 3×2 500 + 4×4 250 = 5 250 + 7 500 + 17 000 = 29 750 €.',
        );
    }
}
