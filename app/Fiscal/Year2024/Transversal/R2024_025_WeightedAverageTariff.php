<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-025 · Moyenne pondérée des tarifs en cas de bascule en cours d'année.
 *
 * **Règle documentaire-only** (ADR-0022 · ajout audit exhaustif 14/05/2026).
 * Quand plusieurs tarifs s'appliquent successivement au cours d'une même
 * année civile pour un même couple (véhicule, entreprise), le tarif
 * annuel utilisé dans le prorata journalier (R-2024-002) est remplacé
 * par la moyenne pondérée des tarifs, chacun pondéré par la durée en
 * nombre de jours de sa période d'application. Si plusieurs tarifs sont
 * susceptibles de s'appliquer le même jour, le tarif le plus élevé est
 * retenu.
 *
 * **Implémentation effective** : la mécanique est déjà appliquée
 * mathématiquement par {@see App\Fiscal\Pipeline\FiscalSegmentedExecutor}
 * via la segmentation par VFC effective (ADR-0021). Pour chaque segment,
 * le tarif annuel est calculé sur la VFC du segment puis proratisé sur
 * ses jours, et tous les segments sont sommés. Mathématiquement
 * équivalent à la moyenne pondérée de L. 421-108.
 *
 * Cette classe n'a pas de logique d'exécution propre · elle existe
 * uniquement pour matérialiser la règle dans le catalogue documentaire
 * et exposer sa traçabilité légale.
 */
final readonly class R2024_025_WeightedAverageTariff implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-025';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Moyenne pondérée des tarifs en cas de bascule en cours d\'année';
    }

    public function description(): string
    {
        return 'Quand un véhicule voit son tarif annuel changer en cours d\'année (changement de caractéristiques fiscales, levée d\'exonération, etc.), le tarif utilisé est la moyenne pondérée par les durées d\'application de chaque tarif. Si plusieurs tarifs sont susceptibles de s\'appliquer le même jour, le tarif le plus élevé est retenu (CIBS art. L. 421-108).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603017/2024-06-01',
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
            title: 'Moyenne pondérée des tarifs en cours d\'année',
            pitch: 'Si les caractéristiques fiscales d\'un véhicule changent en cours d\'année, le tarif annuel utilisé est la moyenne pondérée par durée d\'application.',
            body: 'L\'application gère déjà cette mécanique en interne : elle segmente la période d\'affectation par caractéristiques fiscales effectives et calcule un montant proratisé pour chaque segment, qui sont ensuite sommés. Le résultat est mathématiquement identique à la moyenne pondérée prévue par la loi.',
            example: 'Exemple · véhicule WLTP dont la valeur CO₂ change en milieu d\'année. Du 01/01 au 30/06 (182 jours) : tarif annuel plein 173 €. Du 01/07 au 31/12 (184 jours) : tarif annuel plein 219 €. Tarif moyen pondéré = (173 × 182 + 219 × 184) / 366 ≈ 196,12 €. Affectation toute l\'année : montant dû ≈ 196,12 € (avant arrondi total redevable).',
        );
    }
}
