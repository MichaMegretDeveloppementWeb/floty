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
 * R-2025-010 · Barème CO₂ WLTP 2025 · ⚠️ DURCISSEMENT MAJEUR vs 2024
 * (CIBS art. L. 421-120 version 01/01/2025, modifiée par LF 2024 art. 97, 19°).
 *
 * Tarif progressif par tranches à tarif marginal sur les émissions CO₂
 * WLTP (g/km). **9 tranches**, dernière ouverte (≥ 171 g/km). Tous les
 * seuils ont été abaissés et certains tarifs marginaux relevés vs 2024.
 *
 * **Différences clés vs R-2024-010** :
 * - 1re tranche couvre jusqu'à 9 g/km (vs 14 en 2024).
 * - Bornes intermédiaires durcies (50/58/90/110/130/150/170 vs 55/63/95/115/135/155/175).
 * - Dernière tranche commence à 171 g/km (vs 176 en 2024).
 * - WLTP 100 g/km = 193 € en 2025 (vs 173 € en 2024, +11,6 %).
 *
 * S'exécute uniquement si la méthode CO₂ résolue par
 * {@see R2025_005_Co2MethodSelection} est WLTP. Sinon no-op.
 *
 * Audit Chrome live · Légifrance LEGIARTI000048886183 + BOFiP
 * BOI-AIS-MOB-10-30-20-20250528 (exemple chiffré 100 g/km = 193 €).
 */
final readonly class R2025_010_WltpProgressive implements PricingRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        // Barème WLTP 2025 durci (LF 2024 art. 97, 19°). Sémantique
        // BracketRange : (lowerExclusive, upperInclusive], cohérente
        // avec R-2024-010.
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 9, 0.0),
            new BracketRange(9, 50, 1.0),
            new BracketRange(50, 58, 2.0),
            new BracketRange(58, 90, 3.0),
            new BracketRange(90, 110, 4.0),
            new BracketRange(110, 130, 10.0),
            new BracketRange(130, 150, 50.0),
            new BracketRange(150, 170, 60.0),
            new BracketRange(170, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2025-010';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Barème WLTP 2025';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ WLTP. Durcissement majeur au 01/01/2025 (LF 2024 art. 97, 19°). 100 g/km = 193 € (vs 173 € en 2024).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048886183/2025-01-01',
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
            title: 'Barème CO₂ WLTP 2025 (durcissement majeur vs 2024)',
            pitch: 'Tarif progressif par tranches calculé sur les grammes de CO₂ par km (valeur WLTP). Barème durci au 01/01/2025.',
            body: "Pour chaque tranche traversée par l'émission du véhicule, on multiplie la fraction d'émission tombant dans la tranche par le tarif marginal de cette tranche. La somme donne le tarif annuel plein. Puis : taxe due = tarif annuel plein × (jours utilisés / 365). Le barème a été durci au 01/01/2025 par la LF 2024 art. 97, 19° : seuils abaissés et derniers paliers relevés. Conséquence pour les véhicules essence courants (100-130 g/km) : hausse d'environ 11 à 13 %.",
            progressiveBrackets: new ProgressiveBracketsTable(
                unit: 'g CO₂/km',
                header: ['Tranche', 'Tarif marginal'],
                rows: [
                    new ProgressiveBracketRow(label: 'Jusqu\'à 9', rate: '0 €/g'),
                    new ProgressiveBracketRow(label: 'De 10 à 50', rate: '1 €/g'),
                    new ProgressiveBracketRow(label: 'De 51 à 58', rate: '2 €/g'),
                    new ProgressiveBracketRow(label: 'De 59 à 90', rate: '3 €/g'),
                    new ProgressiveBracketRow(label: 'De 91 à 110', rate: '4 €/g'),
                    new ProgressiveBracketRow(label: 'De 111 à 130', rate: '10 €/g'),
                    new ProgressiveBracketRow(label: 'De 131 à 150', rate: '50 €/g'),
                    new ProgressiveBracketRow(label: 'De 151 à 170', rate: '60 €/g'),
                    new ProgressiveBracketRow(label: 'À partir de 171', rate: '65 €/g'),
                ],
            ),
            example: 'Peugeot 308 WLTP 100 g/km, utilisée 306 jours par ACME en 2025 : tarif plein = 9×0 + 41×1 + 8×2 + 32×3 + 10×4 = 193 €. Taxe due = 193 × 306/365 = 161,77 €.',
        );
    }
}
