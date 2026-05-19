<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Pricing;

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
 * R-2026-012 - 2026 CO₂ Puissance Administrative scale, MAJOR
 * HARDENING vs 2025 (CIBS art. L. 421-122 version 01/01/2026 →
 * 01/01/2027, modified by LF 2024 art. 97-20°).
 *
 * Applied to older vehicles or vehicles without CO₂ data (cascade
 * {@see R2026_005_Co2MethodSelection}). 5 brackets, last one open
 * (≥ 16 CV).
 *
 * Key differences vs R-2025-012: all marginal rates raised by 250 to
 * 500 €/CV:
 * - 1-3 CV: 2 000 €/CV (vs 1 750 in 2025, +250)
 * - 4-6 CV: 3 000 €/CV (vs 2 500 in 2025, +500)
 * - 7-10 CV: 4 500 €/CV (vs 4 250 in 2025, +250)
 * - 11-15 CV: 5 250 €/CV (vs 5 000 in 2025, +250)
 * - 16+ CV: 6 500 €/CV (vs 6 250 in 2025, +250)
 *
 * 10 CV = 33 000 € in 2026 (vs 29 750 € in 2025, +10.9%).
 *
 * Only runs if the CO₂ method resolved by
 * {@see R2026_005_Co2MethodSelection} is PA. No-op otherwise.
 *
 * Source: Légifrance LEGIARTI000048886433 v 2026-01-01.
 */
final readonly class R2026_012_PaProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        // Hardened 2026 PA scale (LF 2024 art. 97-20°).
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 3, 2000.0),
            new BracketRange(3, 6, 3000.0),
            new BracketRange(6, 10, 4500.0),
            new BracketRange(10, 15, 5250.0),
            new BracketRange(15, null, 6500.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2026-012';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Barème PA 2026';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur la puissance administrative (CV). Durcissement majeur au 01/01/2026 (LF 2024 art. 97-20°, programmé en avance par LF 2024). 10 CV = 33 000 € (vs 29 750 € en 2025, +10,9 %).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048886433/2026-01-01',
                'consulted_at' => '2026-05-15',
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
            title: 'Barème Puissance Administrative 2026 (durcissement majeur vs 2025)',
            pitch: 'Tarif progressif par tranches sur la puissance administrative (CV). Appliqué aux véhicules anciens ou sans données CO₂. Barème durci au 01/01/2026 par anticipation programmée de LF 2024.',
            body: 'Pour chaque tranche traversée par la puissance administrative du véhicule, on multiplie la fraction de CV tombant dans la tranche par le tarif marginal. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 365). Le barème a été durci au 01/01/2026 par la LF 2024 art. 97-20° · tous les tarifs marginaux relevés.',
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'CV',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu\'à 3', rate: '2 000 €/CV'),
                    new ProgressiveBracketRow(label: 'De 4 à 6', rate: '3 000 €/CV'),
                    new ProgressiveBracketRow(label: 'De 7 à 10', rate: '4 500 €/CV'),
                    new ProgressiveBracketRow(label: 'De 11 à 15', rate: '5 250 €/CV'),
                    new ProgressiveBracketRow(label: 'À partir de 16', rate: '6 500 €/CV'),
                ],
            ),
            example: 'Véhicule 10 CV utilisé toute l\'année 2026 : tarif plein = 3×2 000 + 3×3 000 + 4×4 500 = 6 000 + 9 000 + 18 000 = 33 000 €.',
        );
    }
}
