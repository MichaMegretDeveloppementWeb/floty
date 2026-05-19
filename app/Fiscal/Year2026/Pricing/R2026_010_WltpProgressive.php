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
 * R-2026-010 - 2026 CO₂ WLTP scale, MAJOR HARDENING vs 2025 (CIBS
 * art. L. 421-120 version 01/01/2026 → 01/01/2027, modified by
 * LF 2024 art. 97-20°, LOI n° 2023-1322 du 29/12/2023).
 *
 * Progressive bracket-based tariff with marginal rate on WLTP CO₂
 * emissions (g/km). 9 brackets, last one open (≥ 166 g/km). All
 * thresholds lowered by 5 g/km vs 2025.
 *
 * Key differences vs R-2025-010:
 * - 1st bracket covers up to 4 g/km (vs 9 in 2025).
 * - Intermediate bounds hardened (45/53/85/105/125/145/165 vs
 *   50/58/90/110/130/150/170).
 * - Last bracket starts at 166 g/km (vs 171 in 2025).
 * - WLTP 100 g/km = 213 € in 2026 (vs 193 € in 2025, +10.4%).
 *
 * Only runs if the CO₂ method resolved by
 * {@see R2026_005_Co2MethodSelection} is WLTP. No-op otherwise.
 *
 * Source: Légifrance LEGIARTI000048886183 v 2026-01-01.
 *
 * BOFiP example § 230 ex1 2026: "en 2026, le tarif annuel est égal à
 * 4 × 0 + (45-4) × 1 + (53-45) × 2 + (85-53) × 3 + (100-85) × 4 = 213 €".
 */
final readonly class R2026_010_WltpProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        // Hardened 2026 WLTP scale (LF 2024 art. 97-20°). BracketRange
        // semantics: (lowerExclusive, upperInclusive], consistent with
        // R-2024-010 and R-2025-010.
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 4, 0.0),
            new BracketRange(4, 45, 1.0),
            new BracketRange(45, 53, 2.0),
            new BracketRange(53, 85, 3.0),
            new BracketRange(85, 105, 4.0),
            new BracketRange(105, 125, 10.0),
            new BracketRange(125, 145, 50.0),
            new BracketRange(145, 165, 60.0),
            new BracketRange(165, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2026-010';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Barème WLTP 2026';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ WLTP. Durcissement majeur au 01/01/2026 (LF 2024 art. 97-20°, programmé en avance par LF 2024 et pas par LF 2026). 100 g/km = 213 € (vs 193 € en 2025, +10,4 %).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048886183/2026-01-01',
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
            title: 'Barème CO₂ WLTP 2026 (durcissement majeur vs 2025)',
            pitch: 'Tarif progressif par tranches calculé sur les grammes de CO₂ par km (valeur WLTP). Barème durci au 01/01/2026 par anticipation programmée de LF 2024.',
            body: "Pour chaque tranche traversée par l'émission du véhicule, on multiplie la fraction d'émission tombant dans la tranche par le tarif marginal de cette tranche. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 365). Le barème a été durci au 01/01/2026 par la LF 2024 art. 97-20° : tous les seuils abaissés de 5 g/km vs 2025. Conséquence pour les véhicules essence courants (100-130 g/km) : hausse d'environ 10 à 14 % vs 2025.",
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'g CO₂/km',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu\'à 4', rate: '0 €/g'),
                    new ProgressiveBracketRow(label: 'De 5 à 45', rate: '1 €/g'),
                    new ProgressiveBracketRow(label: 'De 46 à 53', rate: '2 €/g'),
                    new ProgressiveBracketRow(label: 'De 54 à 85', rate: '3 €/g'),
                    new ProgressiveBracketRow(label: 'De 86 à 105', rate: '4 €/g'),
                    new ProgressiveBracketRow(label: 'De 106 à 125', rate: '10 €/g'),
                    new ProgressiveBracketRow(label: 'De 126 à 145', rate: '50 €/g'),
                    new ProgressiveBracketRow(label: 'De 146 à 165', rate: '60 €/g'),
                    new ProgressiveBracketRow(label: 'À partir de 166', rate: '65 €/g'),
                ],
            ),
            example: 'Peugeot 308 WLTP 100 g/km, utilisée 365 jours par ACME en 2026 : tarif plein = 4×0 + 41×1 + 8×2 + 32×3 + 15×4 = 213 € (exemple BOFiP officiel § 230 ex1). Taxe due = 213 × 365/365 = 213 €.',
        );
    }
}
