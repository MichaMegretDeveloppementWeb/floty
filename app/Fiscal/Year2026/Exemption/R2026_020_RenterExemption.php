<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-020 - Renter exemption: taxpayer = user company.
 *
 * Documentation-only rule, strict reproduction of R-2025-020. The
 * renter (company that holds vehicles to rent them out or make them
 * available to its clients) is not the taxpayer; the user companies
 * are.
 *
 * Mirror article pair (identical text word for word):
 * - L. 421-128 (CO₂ tax): LEGIARTI000044602959
 * - L. 421-140 (pollutants tax): LEGIARTI000044602921
 *
 * Locked URL: L. 421-140 must point to LEGIARTI000044602921 (not
 * LEGIARTI000044602919, which designates L. 421-141 on LCD).
 *
 * L. 421-128 and L. 421-140 unchanged since 01/01/2022, not modified
 * by LF 2026 or Ordo 2025-1247.
 */
final readonly class R2026_020_RenterExemption implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-020';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Exonération loueur - redevable = entreprise utilisatrice';
    }

    public function description(): string
    {
        return "Le loueur (entreprise qui détient les véhicules pour les louer ou mettre à disposition de ses clients) n'est pas redevable de la taxe · ce sont les entreprises utilisatrices qui le sont. Texte identique pour les deux taxes (L. 421-140 reprend L. 421-128 mot pour mot). Reconduction stricte 2025 → 2026.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602959/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-140',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602921/2026-01-01',
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
            tab: RuleTab::Calcul,
            section: RuleSection::Exoneration,
            title: "Exonération loueur (fondement du modèle de l'application)",
            pitch: 'La société de location ne paie aucune taxe sur ses véhicules en stock. Seule la part louée à une entreprise utilisatrice déclenche une taxe.',
            appliesWhen: "Le véhicule est détenu par une société dont l'activité est la location. Exonération applicable sur les jours où le véhicule n'est attribué à aucune entreprise utilisatrice.",
            effect: "Aucune ligne fiscale n'est produite pour le bailleur. Les entreprises utilisatrices paient au prorata de leur usage effectif · si un véhicule est utilisé 350 jours/365 en cumul, il reste 15 jours non taxés (stock bailleur).",
            example: 'Peugeot 308 propriété de la société Renaud, 2026 (WLTP 100 g/km, Cat1 polluants) · A 200 j, B 100 j, C 50 j, 15 j en stock. Tarif plein pondéré = 213 € (CO₂) + 125,15 € (polluants moyenne pondérée 2026) = 338,15 €. A paie 185,29 €, B 92,64 €, C 46,32 € · bailleur 0 €.',
        );
    }
}
