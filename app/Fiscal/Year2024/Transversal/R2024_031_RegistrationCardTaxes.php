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
 * R-2024-031 · taxes attached to the registration certificate.
 *
 * Documentary-only rule, OUT OF FLOTY SCOPE.
 *
 * Groups three one-shot taxes paid when the registration certificate
 * is issued:
 *   - Regional tax Y1 (CIBS L. 421-41 to L. 421-54-1) · collected by
 *     the region, based on horsepower (CV) at the regional rate.
 *     Average 53,39 €/CV in 2024.
 *   - Tax Y2 (CIBS L. 421-55 to L. 421-57) · for the development of
 *     professional training in road transport, applicable to LCV and
 *     trucks.
 *   - Flat tax Y4 (CIBS L. 421-49) · flat 11 € on each issuance.
 *
 * Taxpayer: holder of the registration certificate.
 *
 * Why out of Floty scope: same as malus. The renter registers and
 * pays these taxes. Using companies are never directly liable (even
 * though LLD rent re-invoicing is common).
 */
final readonly class R2024_031_RegistrationCardTaxes implements InformativeRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-031';
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
        return 'Taxes liées au certificat d\'immatriculation (carte grise)';
    }

    public function description(): string
    {
        return "Trois taxes ponctuelles acquittées lors de l'établissement de la carte grise · taxe régionale Y1 (tarif au cheval fiscal, variable par région · moyenne 53,39 €/CV en 2024), taxe Y2 (formation professionnelle transport routier, pour VUL et camions), taxe fixe Y4 (11 €). Redevable · titulaire de la carte grise. Hors périmètre de l'application · le bailleur immatricule les véhicules · les entreprises utilisatrices ne sont jamais directement redevables.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 31;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-41 à L. 421-54-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2024-06-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-49 (taxe fixe Y4)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2024-06-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'ANTS · Taxes sur les cartes grises',
                'url' => 'https://immatriculation.ants.gouv.fr/tout-savoir/taxes-sur-les-cartes-grises',
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
            title: 'Taxes liées au certificat d\'immatriculation',
            pitch: 'Taxes acquittées à chaque délivrance d\'une carte grise · taxe régionale (au cheval fiscal), taxe formation transport, taxe fixe.',
            body: "Acquittées par le titulaire de la carte grise · dans le modèle de l'application, le bailleur immatricule les véhicules et acquitte ces taxes ponctuelles. Les entreprises utilisatrices ne sont jamais directement redevables. Documentées pour exhaustivité.",
        );
    }
}
