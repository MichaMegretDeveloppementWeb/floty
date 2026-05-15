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
 * R-2026-011 · Barème CO₂ NEDC 2026 · ⚠️ DURCISSEMENT MAJEUR vs 2025
 * (CIBS art. L. 421-121 version 01/01/2026 → 01/01/2027, modifiée par
 * LF 2024 art. 97-20°).
 *
 * Concerne les véhicules immatriculés en France entre le 01/06/2004 et
 * le 29/02/2020 et homologués en cycle NEDC. **9 tranches**, dernière
 * ouverte (≥ 137 g/km).
 *
 * **Différences clés vs R-2025-011** ·
 * - 1re tranche couvre jusqu'à 3 g/km (vs 7 en 2025).
 * - Bornes intermédiaires durcies (37/44/70/87/103/120/136 vs 41/48/74/91/107/124/140).
 * - Dernière tranche commence à 137 g/km (vs 141 en 2025).
 * - NEDC 100 g/km = **324 € en 2026** (vs 284 € en 2025, +14,1 %).
 *
 * S'exécute uniquement si la méthode CO₂ résolue par
 * {@see R2026_005_Co2MethodSelection} est NEDC. Sinon no-op.
 *
 * **Source légale primaire** · Légifrance LEGIARTI000048886368 v 2026-01-01
 * (audité Chrome live 15/05/2026).
 */
final readonly class R2026_011_NedcProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        // Barème NEDC 2026 durci (LF 2024 art. 97-20°).
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 3, 0.0),
            new BracketRange(3, 37, 1.0),
            new BracketRange(37, 44, 2.0),
            new BracketRange(44, 70, 3.0),
            new BracketRange(70, 87, 4.0),
            new BracketRange(87, 103, 10.0),
            new BracketRange(103, 120, 50.0),
            new BracketRange(120, 136, 60.0),
            new BracketRange(136, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2026-011';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Barème NEDC 2026';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ NEDC. Durcissement majeur au 01/01/2026 (LF 2024 art. 97-20°, programmé en avance par LF 2024). 100 g/km = 324 € (vs 284 € en 2025, +14,1 %).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048886368/2026-01-01',
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
            title: 'Barème CO₂ NEDC 2026 (véhicules 2004-2020 · durcissement majeur vs 2025)',
            pitch: 'Tarif progressif par tranches calculé sur les grammes de CO₂ par km (valeur NEDC). Barème durci au 01/01/2026 par anticipation programmée de LF 2024.',
            body: "Concerne les véhicules immatriculés en France entre le 01/06/2004 et le 29/02/2020 et homologués en cycle NEDC. Pour chaque tranche traversée, on multiplie la fraction d'émission tombant dans la tranche par le tarif marginal de cette tranche. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 365). Le barème a été durci au 01/01/2026 par la LF 2024 art. 97-20°.",
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'g CO₂/km',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu\'à 3', rate: '0 €/g'),
                    new ProgressiveBracketRow(label: 'De 4 à 37', rate: '1 €/g'),
                    new ProgressiveBracketRow(label: 'De 38 à 44', rate: '2 €/g'),
                    new ProgressiveBracketRow(label: 'De 45 à 70', rate: '3 €/g'),
                    new ProgressiveBracketRow(label: 'De 71 à 87', rate: '4 €/g'),
                    new ProgressiveBracketRow(label: 'De 88 à 103', rate: '10 €/g'),
                    new ProgressiveBracketRow(label: 'De 104 à 120', rate: '50 €/g'),
                    new ProgressiveBracketRow(label: 'De 121 à 136', rate: '60 €/g'),
                    new ProgressiveBracketRow(label: 'À partir de 137', rate: '65 €/g'),
                ],
            ),
            example: 'Peugeot 207 NEDC 100 g/km, utilisée toute l\'année 2026 : tarif plein = 3×0 + 34×1 + 7×2 + 26×3 + 17×4 + 13×10 = 324 €. Taxe due = 324 € (365/365).',
        );
    }
}
