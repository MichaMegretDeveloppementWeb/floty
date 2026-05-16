<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-030 · Malus au poids à l'immatriculation (« taxe sur la masse
 * en ordre de marche »).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Taxe ponctuelle à l'immatriculation d'un véhicule de tourisme dont
 * la masse en ordre de marche dépasse un seuil.
 *
 * **Redevable** · titulaire du certificat d'immatriculation (idem
 * malus CO₂).
 *
 * **Pourquoi hors périmètre Floty** · même raison que le malus CO₂ ·
 * dans le modèle Floty, le bailleur (société de location) immatricule
 * les véhicules et acquitte le malus poids. Les entreprises
 * utilisatrices ne sont jamais redevables.
 *
 * **Paramètres 2024** · seuil 1 600 kg, barème 10 €/kg au-dessus du
 * seuil, abattement familial de 70 kg par enfant à charge à partir
 * du 3ᵉ enfant. Exonération totale pour véhicules à motorisation
 * exclusivement électrique ou hydrogène (cf. L. 421-71 et suivants).
 */
final readonly class R2024_030_RegistrationWeightMalus implements InformativeRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-030';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Malus au poids à l\'immatriculation';
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme dont la masse en ordre de marche dépasse 1 600 kg en 2024. Barème de 10 €/kg au-dessus du seuil, avec abattement familial et exonération totale pour les véhicules électriques ou hydrogène. Hors périmètre de l'application · le bailleur (société de location) acquitte le malus, les entreprises utilisatrices ne sont jamais redevables.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 30;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-71 à L. 421-81-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2024-06-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250528',
                'consulted_at' => '2026-04-22',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'F35950',
                'url' => 'https://www.service-public.gouv.fr/particuliers/vosdroits/F35950',
                'consulted_at' => '2026-04-22',
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
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::TaxeConnexe,
            title: 'Malus au poids à l\'immatriculation',
            pitch: 'Taxe ponctuelle payée à l\'achat d\'un véhicule neuf dont la masse en ordre de marche dépasse 1 600 kg en 2024.',
            body: "Acquittée par le titulaire de la carte grise (le bailleur dans le modèle de l'application). Barème 10 €/kg au-dessus du seuil, avec abattement familial pour les familles nombreuses et exonération totale pour les véhicules électriques ou hydrogène. Hors périmètre de l'application · les entreprises utilisatrices ne sont pas redevables.",
            example: 'Un SUV thermique de 1 900 kg immatriculé en 2024 est soumis à un malus de 300 kg × 10 €/kg = 3 000 €, acquitté par le bailleur lors de l\'immatriculation.',
        );
    }
}
