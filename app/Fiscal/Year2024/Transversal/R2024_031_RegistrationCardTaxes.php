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
 * R-2024-031 · Taxes liées au certificat d'immatriculation (carte grise).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Regroupe trois taxes ponctuelles acquittées lors de l'établissement
 * d'un certificat d'immatriculation :
 *   - Taxe régionale Y1 (CIBS art. L. 421-41 à L. 421-54-1) · perçue
 *     par la région, basée sur la puissance fiscale (CV) avec tarif
 *     régional du cheval. Moyenne 53,39 €/CV en 2024.
 *   - Taxe Y2 (CIBS art. L. 421-55 à L. 421-57) · pour le développement
 *     des actions de formation professionnelle dans les transports
 *     routiers, applicable aux VUL et camions.
 *   - Taxe fixe Y4 (CIBS art. L. 421-49) · 11 € fixe à chaque
 *     délivrance.
 *
 * **Redevable** · titulaire du certificat d'immatriculation.
 *
 * **Pourquoi hors périmètre Floty** · idem malus · le bailleur
 * immatricule et acquitte ces taxes. Les entreprises utilisatrices
 * ne sont jamais directement redevables (même si refacturation dans
 * le loyer LLD est habituelle).
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
        return 'Trois taxes ponctuelles acquittées lors de l\'établissement de la carte grise · taxe régionale Y1 (tarif au cheval fiscal, variable par région · moyenne 53,39 €/CV en 2024), taxe Y2 (formation professionnelle transport routier, pour VUL et camions), taxe fixe Y4 (11 €). Redevable · titulaire de la carte grise. Hors périmètre Floty · le bailleur immatricule les véhicules · les entreprises utilisatrices ne sont jamais directement redevables.';
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
                'type' => 'CIBS',
                'article' => 'L. 421-55 à L. 421-57 (taxe Y2)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599043/2024-06-01/',
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
            tab: RuleTab::Cadre,
            section: RuleSection::TaxeConnexe,
            title: 'Taxes liées au certificat d\'immatriculation',
            pitch: 'Taxes acquittées à chaque délivrance d\'une carte grise · taxe régionale (au cheval fiscal), taxe formation transport, taxe fixe.',
            body: 'Acquittées par le titulaire de la carte grise · dans le modèle Floty, le bailleur immatricule les véhicules et acquitte ces taxes ponctuelles. Les entreprises utilisatrices ne sont jamais directement redevables. Documentées pour exhaustivité.',
        );
    }
}
