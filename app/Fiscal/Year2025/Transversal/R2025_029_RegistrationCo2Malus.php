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
 * R-2025-029 · Malus CO₂ à l'immatriculation · ⚠️ DURCISSEMENT 01/03/2025.
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Taxe ponctuelle à l'immatriculation d'un véhicule de tourisme neuf
 * (ou occasion importée non précédemment immatriculée en France).
 *
 * **Évolution majeure 2025** (LF 2025 art. 28) :
 * - Seuil de déclenchement **abaissé à 113 g CO₂/km** (vs 118 en 2024) au 01/03/2025.
 * - Plafond **rehaussé à 70 000 €** (vs 60 000 € en 2024).
 * - **Suppression du plafonnement à 50 % du prix d'acquisition**.
 *
 * **Redevable** · titulaire du certificat d'immatriculation (idem 2024).
 *
 * **Pourquoi hors périmètre Floty** · dans le modèle Floty, les
 * véhicules de la flotte sont détenus par une **société de location**
 * qui les immatricule à son nom. C'est donc le bailleur qui acquitte
 * le malus CO₂ à l'achat. Les entreprises utilisatrices, qui prennent
 * en location longue durée, ne sont **jamais redevables** de cette
 * taxe ponctuelle.
 */
final readonly class R2025_029_RegistrationCo2Malus implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-029';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return "Malus CO₂ à l'immatriculation (durci au 01/03/2025)";
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme. Durcissement majeur au 01/03/2025 (LF 2025 art. 28) · seuil de déclenchement abaissé à 113 g CO₂/km (vs 118 g en 2024), plafond rehaussé à 70 000 € (vs 60 000 €), suppression du plafonnement à 50 % du prix d'acquisition. Hors périmètre Floty · dans le modèle de flotte partagée, le véhicule est immatriculé au nom de la société de location qui acquitte le malus · les entreprises utilisatrices ne sont jamais redevables (même si le coût peut être refacturé dans le loyer).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 29;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-58 à L. 421-70-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2025-03-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
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
            section: RuleSection::TaxeConnexe,
            title: "Malus CO₂ à l'immatriculation (durci 2025)",
            pitch: "Taxe ponctuelle payée à l'achat d'un véhicule neuf, en fonction de ses émissions de CO₂. Barème durci au 01/03/2025.",
            body: "Acquittée par le titulaire de la carte grise · dans le modèle Floty, c'est la société de location qui détient et immatricule les véhicules · elle est donc redevable du malus à l'achat. Les entreprises utilisatrices, qui prennent en location longue durée, ne sont jamais redevables de cette taxe ponctuelle. Documentée ici pour exhaustivité du panorama fiscal véhicules. Évolution 2025 · seuil abaissé à 113 g, plafond rehaussé à 70 000 €, suppression du plafonnement à 50 % du prix.",
            example: "En 2025, un véhicule neuf émettant 150 g CO₂/km est soumis au malus (au-dessus du seuil de 113 g). Le bailleur acquitte le malus correspondant lors de l'immatriculation, généralement répercuté dans le loyer LLD facturé à l'entreprise utilisatrice · sans figurer dans le calcul Floty des taxes annuelles.",
        );
    }
}
