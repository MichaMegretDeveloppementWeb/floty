<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-025 · Moyenne pondérée des tarifs en cas de bascule en cours
 * d'année.
 *
 * **Règle documentaire-only** · reconduction stricte de R-2025-025
 * avec dénominateur **365** (2026 non bissextile). CIBS L. 421-108
 * inchangé depuis 01/01/2022 (audit Chrome live 15/05/2026 confirme
 * aucune modification par LF 2026 ni par Ordo 2025-1247).
 *
 * **Implémentation effective** · la mécanique est déjà appliquée
 * mathématiquement par {@see App\Fiscal\Pipeline\FiscalSegmentedExecutor}
 * via la segmentation par VFC effective (ADR-0021). Pour chaque segment,
 * le tarif annuel est calculé sur la VFC du segment puis proratisé sur
 * ses jours, et tous les segments sont sommés. Mathématiquement
 * équivalent à la moyenne pondérée de L. 421-108.
 *
 * **Cas particulier 2026** · la scission matérielle R-2026-014 /
 * R-2026-014-bis (revalorisation polluants +30 % au 01/03/2026) déclenche
 * **systématiquement** la segmentation pour tout contrat LLD à cheval
 * sur le 01/03/2026 · exemple Cat1 LLD full-year 2026 ·
 * `(100 × 59 + 130 × 306) / 365 = 125,15 €`. Le `FiscalSegmentedExecutor`
 * gère ceci sans intervention applicative.
 */
final readonly class R2026_025_WeightedAverageTariff implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-025';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return "Moyenne pondérée des tarifs en cas de bascule en cours d'année";
    }

    public function description(): string
    {
        return "Quand un véhicule voit son tarif annuel changer en cours d'année (changement de caractéristiques fiscales, levée d'exonération, scission ADR-0022 type R-2026-014/014-bis), le tarif utilisé est la moyenne pondérée par les durées d'application de chaque tarif. Si plusieurs tarifs sont susceptibles de s'appliquer le même jour, le tarif le plus élevé est retenu (CIBS art. L. 421-108). Reconduction stricte 2025 → 2026 avec dénominateur 365 (2026 non bissextile).";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603017/2026-01-01',
                'consulted_at' => '2026-05-15',
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
            pitch: "Si les caractéristiques fiscales d'un véhicule changent en cours d'année, ou si une scission de barème intervient (cas R-2026-014/014-bis le 01/03/2026), le tarif annuel utilisé est la moyenne pondérée par durée d'application.",
            body: "L'application gère déjà cette mécanique en interne · elle segmente la période d'affectation par caractéristiques fiscales effectives et calcule un montant proratisé pour chaque segment, qui sont ensuite sommés. Le résultat est mathématiquement identique à la moyenne pondérée prévue par la loi. En 2026, la scission matérielle des tarifs polluants au 01/03/2026 (revalorisation +30 % par LF 2026 art. 58 V IV) impose la segmentation systématique pour tout contrat LLD à cheval sur cette date.",
            example: "Exemple polluants 2026 · véhicule Cat1 LLD du 01/01 au 31/12/2026. Du 01/01 au 28/02 (59 j) · tarif annuel 100 € (R-2026-014). Du 01/03 au 31/12 (306 j) · tarif annuel 130 € (R-2026-014-bis). Tarif moyen pondéré = (100 × 59 + 130 × 306) / 365 = 125,15 €/an. Affectation toute l'année · montant dû = 125,15 € (avant arrondi total redevable).",
        );
    }
}
