<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-006 - PA scale fallback (CO₂ missing).
 *
 * Documentation-only rule, strict reproduction of R-2025-006. Fiscal
 * fallback doctrine: when CO₂ emissions data is missing on the VFC,
 * the Puissance Administrative (PA) scale is applied instead of the
 * WLTP/NEDC scale. Effective method selection is performed by
 * {@see App\Fiscal\Year2026\Classification\R2026_005_Co2MethodSelection}.
 *
 * CIBS L. 421-119-1 unchanged (neither LF 2026 nor Ordo 2025-1247
 * touch this article).
 */
final readonly class R2026_006_PaFallback implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-006';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Bascule sur barème PA (CO₂ manquant)';
    }

    public function description(): string
    {
        return 'Fallback vers le barème Puissance Administrative si la donnée CO₂ est manquante. Reconduction stricte 2025 → 2026.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        return 6;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-119-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2026-01-01',
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreEvenement,
            title: 'Bascule automatique sur barème PA',
            pitch: "Quand la donnée CO₂ attendue est manquante, l'application bascule automatiquement sur le barème Puissance Administrative.",
            body: "Complémentaire de la règle de sélection du barème CO₂ (R-2026-005) · si un véhicule post-2020 devrait relever du WLTP mais n'a pas de CO₂ WLTP saisi, le calcul se rabat sur les CV. Un indicateur UI signale ces véhicules pour inciter l'utilisateur à compléter les données. En 2026, l'impact économique d'une bascule est plus marqué qu'en 2025 du fait du durcissement majeur du barème PA (10 CV = 33 000 € vs 29 750 € en 2025, +10,9 %).",
        );
    }
}
