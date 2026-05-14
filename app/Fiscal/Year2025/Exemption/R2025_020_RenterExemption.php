<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-020 · Exonération loueur · redevable = entreprise utilisatrice.
 *
 * **Règle documentaire-only** · reconduction stricte de R-2024-020.
 * Le loueur (entreprise qui détient des véhicules pour les louer ou
 * les mettre à disposition de ses clients) n'est pas redevable de la
 * taxe · ce sont les entreprises utilisatrices qui le sont. Texte
 * CIBS L. 421-128 inchangé depuis 01/01/2022 (miroir L. 421-140
 * identique).
 */
final readonly class R2025_020_RenterExemption implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-020';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Exonération loueur - redevable = entreprise utilisatrice';
    }

    public function description(): string
    {
        return "Le loueur (entreprise qui détient les véhicules pour les louer ou mettre à disposition de ses clients) n'est pas redevable de la taxe · ce sont les entreprises utilisatrices qui le sont. Texte identique pour les deux taxes (L. 421-140 reprend L. 421-128 mot pour mot). Reconduction stricte 2024.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 20;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-128',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602959/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-140',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602919/2025-01-01',
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
            tab: RuleTab::Calcul,
            section: RuleSection::Exoneration,
            title: "Exonération loueur (fondement du modèle de l'application)",
            pitch: 'La société de location ne paie aucune taxe sur ses véhicules en stock. Seule la part louée à une entreprise utilisatrice déclenche une taxe.',
            appliesWhen: "Le véhicule est détenu par une société dont l'activité est la location. Exonération applicable sur les jours où le véhicule n'est attribué à aucune entreprise utilisatrice.",
            effect: "Aucune ligne fiscale n'est produite pour le bailleur. Les entreprises utilisatrices paient au prorata de leur usage effectif · si un véhicule est utilisé 350 jours/365 en cumul, il reste 15 jours non taxés (stock bailleur).",
            example: 'Peugeot 308 propriété de la société Renaud, 2025 · A 200 j, B 100 j, C 50 j, 15 j en stock. Tarif plein (CO₂ 193 + polluants 100) = 293 €. A paie 160,55 €, B 80,27 €, C 40,14 € · bailleur 0 €.',
        );
    }
}
