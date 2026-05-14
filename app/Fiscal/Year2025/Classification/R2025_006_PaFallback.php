<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-006 · Bascule sur barème PA (CO₂ manquant).
 *
 * **Règle documentaire-only** · reconduction stricte de R-2024-006.
 * Doctrine de fallback fiscale · si la donnée d'émissions CO₂ est
 * manquante sur la VFC, le barème Puissance Administrative (PA) est
 * appliqué à la place du barème WLTP/NEDC. La sélection effective de
 * méthode est faite par {@see App\Fiscal\Year2025\Classification\R2025_005_Co2MethodSelection}.
 */
final readonly class R2025_006_PaFallback implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-006';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Bascule sur barème PA (CO₂ manquant)';
    }

    public function description(): string
    {
        return 'Fallback vers le barème Puissance Administrative si la donnée CO₂ est manquante. Reconduction stricte 2024.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2025-01-01',
                'consulted_at' => '2026-05-14',
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
            body: "Complémentaire de la règle de sélection du barème CO₂ (R-2025-005) · si un véhicule post-2020 devrait relever du WLTP mais n'a pas de CO₂ WLTP saisi, le calcul se rabat sur les CV. Un indicateur UI signale ces véhicules pour inciter l'utilisateur à compléter les données.",
        );
    }
}
