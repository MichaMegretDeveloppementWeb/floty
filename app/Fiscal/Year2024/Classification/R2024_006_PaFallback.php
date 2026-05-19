<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-006 · fallback to PA bracket (missing CO₂).
 *
 * Documentary-only rule (ADR-0022). Describes the fiscal fallback
 * doctrine: when the CO₂ emissions value is missing on the VFC, the
 * Administrative Horsepower (PA) bracket is applied instead of the
 * WLTP/NEDC bracket. Actual method selection is done by the pipeline
 * rule {@see R2024_005_Co2MethodSelection}.
 */
final readonly class R2024_006_PaFallback implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-006';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Bascule sur barème PA (CO₂ manquant)';
    }

    public function description(): string
    {
        return 'Fallback vers le barème Puissance Administrative si la donnée CO₂ est manquante.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2024-06-01',
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreEvenement,
            title: 'Bascule automatique sur barème PA',
            pitch: "Quand la donnée CO₂ attendue est manquante, l'application bascule automatiquement sur le barème Puissance Administrative.",
            body: "Complémentaire de la règle de sélection du barème CO₂ (R-2024-005) : si un véhicule post-2020 devrait relever du WLTP mais n’a pas de CO₂ WLTP saisi, le calcul se rabat sur les CV. Un indicateur UI signale ces véhicules pour inciter l'utilisateur à compléter les données.",
        );
    }
}
