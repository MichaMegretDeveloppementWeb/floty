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
 * R-2024-029 · Malus CO₂ à l'immatriculation (« malus écologique »).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Taxe ponctuelle à l'immatriculation d'un véhicule de tourisme neuf
 * (ou occasion importée non précédemment immatriculée en France),
 * basée sur les émissions de CO₂.
 *
 * **Redevable** · titulaire du certificat d'immatriculation, c'est-à-dire
 * la personne au nom de laquelle la carte grise est établie.
 *
 * **Pourquoi hors périmètre Floty** · dans le modèle Floty, les
 * véhicules de la flotte sont détenus par une **société de location**
 * qui les immatricule à son nom. C'est donc le bailleur qui acquitte
 * le malus CO₂ à l'achat. Les entreprises utilisatrices, qui prennent
 * en location longue durée, ne sont **jamais redevables** de cette
 * taxe ponctuelle (même si le coût peut être refacturé dans le loyer,
 * la redevabilité juridique reste au bailleur).
 *
 * **Paramètres 2024** · seuil de déclenchement 118 g CO₂/km, plafond
 * 60 000 €, plafonnement à 50 % du prix d'acquisition (LF 2024 art. 100).
 * Détails sur Légifrance et BOFiP-AIS-MOB-10-20-40.
 */
final readonly class R2024_029_RegistrationCo2Malus implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-029';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Malus CO₂ à l\'immatriculation';
    }

    public function description(): string
    {
        return 'Taxe ponctuelle acquittée par le titulaire du certificat d\'immatriculation lors de la première immatriculation en France d\'un véhicule de tourisme. Pour 2024 · seuil de déclenchement à 118 g CO₂/km, plafond à 60 000 €, plafonnement à 50 % du prix d\'acquisition. Hors périmètre Floty · dans le modèle de flotte partagée, le véhicule est immatriculé au nom de la société de location qui acquitte le malus · les entreprises utilisatrices ne sont jamais redevables (même si le coût peut être refacturé dans le loyer).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2024-06-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250528',
                'consulted_at' => '2026-04-22',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        // Cette taxe est distincte des deux taxes annuelles Floty (CO₂
        // d'affectation et polluants). Elle a son propre régime ·
        // déclarée pour exhaustivité, non rattachée à TaxType::Co2 ni
        // TaxType::Pollutants. On utilise les deux valeurs uniquement
        // pour satisfaire le contrat (`taxesConcerned()` non vide), mais
        // la règle est hors périmètre Floty et n'entre dans aucun
        // calcul.
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::TaxeConnexe,
            title: 'Malus CO₂ à l\'immatriculation',
            pitch: 'Taxe ponctuelle payée à l\'achat d\'un véhicule neuf, en fonction de ses émissions de CO₂.',
            body: 'Acquittée par le titulaire de la carte grise · dans le modèle Floty, c\'est la société de location qui détient et immatricule les véhicules · elle est donc redevable du malus à l\'achat. Les entreprises utilisatrices, qui prennent en location longue durée, ne sont jamais redevables de cette taxe ponctuelle. Documentée ici pour exhaustivité du panorama fiscal véhicules.',
            example: 'En 2024, un véhicule neuf émettant 150 g CO₂/km est soumis au malus (au-dessus du seuil de 118 g). Le bailleur acquitte le malus correspondant lors de l\'immatriculation, généralement répercuté dans le loyer LLD facturé à l\'entreprise utilisatrice · sans figurer dans le calcul Floty des taxes annuelles.',
        );
    }
}
