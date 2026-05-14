<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-025 · Moyenne pondérée des tarifs en cas de bascule en cours d'année.
 *
 * **Règle documentaire-only** · reconduction stricte de R-2024-025
 * avec dénominateur **365** (2025 non bissextile). CIBS L. 421-108
 * inchangé depuis 01/01/2022.
 *
 * **Implémentation effective** · la mécanique est déjà appliquée
 * mathématiquement par {@see App\Fiscal\Pipeline\FiscalSegmentedExecutor}
 * via la segmentation par VFC effective (ADR-0021). Pour chaque segment,
 * le tarif annuel est calculé sur la VFC du segment puis proratisé sur
 * ses jours, et tous les segments sont sommés. Mathématiquement
 * équivalent à la moyenne pondérée de L. 421-108.
 */
final readonly class R2025_025_WeightedAverageTariff implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-025';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return "Moyenne pondérée des tarifs en cas de bascule en cours d'année";
    }

    public function description(): string
    {
        return "Quand un véhicule voit son tarif annuel changer en cours d'année (changement de caractéristiques fiscales, levée d'exonération, etc.), le tarif utilisé est la moyenne pondérée par les durées d'application de chaque tarif. Si plusieurs tarifs sont susceptibles de s'appliquer le même jour, le tarif le plus élevé est retenu (CIBS art. L. 421-108). Reconduction stricte 2024 avec dénominateur 365 (2025 non bissextile).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 25;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-108',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603017/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreImplicite,
            title: "Moyenne pondérée des tarifs en cours d'année",
            pitch: "Si les caractéristiques fiscales d'un véhicule changent en cours d'année, le tarif annuel utilisé est la moyenne pondérée par durée d'application.",
            body: "L'application gère déjà cette mécanique en interne · elle segmente la période d'affectation par caractéristiques fiscales effectives et calcule un montant proratisé pour chaque segment, qui sont ensuite sommés. Le résultat est mathématiquement identique à la moyenne pondérée prévue par la loi.",
            example: "Exemple · véhicule WLTP dont la valeur CO₂ change le 01/07/2025. Du 01/01 au 30/06/2025 (181 j) · tarif annuel plein 193 €. Du 01/07 au 31/12/2025 (184 j) · tarif annuel plein 433 €. Tarif moyen pondéré = (193 × 181 + 433 × 184) / 365 ≈ 313,99 €. Affectation toute l'année · montant dû ≈ 313,99 € (avant arrondi total redevable).",
        );
    }
}
