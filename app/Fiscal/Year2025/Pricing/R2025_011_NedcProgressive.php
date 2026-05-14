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
 * R-2025-011 · Barème CO₂ NEDC 2025 · ⚠️ DURCISSEMENT MAJEUR vs 2024
 * (CIBS art. L. 421-121 version 01/01/2025, modifiée par LF 2024 art. 97, 19°).
 *
 * Tarif progressif par tranches à tarif marginal sur les émissions CO₂
 * NEDC (g/km). **9 tranches**, dernière ouverte (≥ 141 g/km).
 *
 * **Différences clés vs R-2024-011** :
 * - 1re tranche jusqu'à 7 g/km (vs 12 en 2024).
 * - Bornes durcies.
 * - NEDC 100 g/km = 284 € en 2025.
 *
 * S'exécute uniquement si la méthode CO₂ résolue par
 * {@see R2025_005_Co2MethodSelection} est NEDC. Sinon no-op.
 *
 * Audit Chrome live · Légifrance LEGIARTI000048886368.
 */
final readonly class R2025_011_NedcProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        // Barème NEDC 2025 durci (LF 2024 art. 97, 19°).
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 7, 0.0),
            new BracketRange(7, 41, 1.0),
            new BracketRange(41, 48, 2.0),
            new BracketRange(48, 74, 3.0),
            new BracketRange(74, 91, 4.0),
            new BracketRange(91, 107, 10.0),
            new BracketRange(107, 124, 50.0),
            new BracketRange(124, 140, 60.0),
            new BracketRange(140, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2025-011';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Barème NEDC 2025';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ NEDC. Durcissement majeur au 01/01/2025 (LF 2024 art. 97, 19°). 100 g/km = 284 €.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048886368/2025-01-01',
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
            title: 'Barème CO₂ NEDC 2025 (véhicules 2004-2020 · durcissement majeur vs 2024)',
            pitch: 'Tarif progressif par tranches calculé sur les grammes de CO₂ par km (valeur NEDC). Barème durci au 01/01/2025.',
            body: "Concerne les véhicules immatriculés en France entre le 01/06/2004 et le 29/02/2020 et homologués en cycle NEDC. Pour chaque tranche traversée, on multiplie la fraction d'émission tombant dans la tranche par le tarif marginal de cette tranche. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 365). Le barème a été durci au 01/01/2025 par la LF 2024 art. 97, 19°.",
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'g CO₂/km',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu\'à 7', rate: '0 €/g'),
                    new ProgressiveBracketRow(label: 'De 8 à 41', rate: '1 €/g'),
                    new ProgressiveBracketRow(label: 'De 42 à 48', rate: '2 €/g'),
                    new ProgressiveBracketRow(label: 'De 49 à 74', rate: '3 €/g'),
                    new ProgressiveBracketRow(label: 'De 75 à 91', rate: '4 €/g'),
                    new ProgressiveBracketRow(label: 'De 92 à 107', rate: '10 €/g'),
                    new ProgressiveBracketRow(label: 'De 108 à 124', rate: '50 €/g'),
                    new ProgressiveBracketRow(label: 'De 125 à 140', rate: '60 €/g'),
                    new ProgressiveBracketRow(label: 'À partir de 141', rate: '65 €/g'),
                ],
            ),
            example: 'Peugeot 207 NEDC 100 g/km, utilisée toute l\'année 2025 : tarif plein = 7×0 + 34×1 + 7×2 + 26×3 + 17×4 + 9×10 = 284 €. Taxe due = 284 € (365/365).',
        );
    }
}
