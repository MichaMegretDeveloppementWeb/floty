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
 * R-2025-031 · Taxes liées au certificat d'immatriculation · évolution 01/05/2025.
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Regroupe trois taxes ponctuelles acquittées lors de l'établissement
 * d'un certificat d'immatriculation :
 *   - Taxe régionale Y1 (CIBS art. L. 421-41 à L. 421-54-1).
 *   - Taxe Y2 (CIBS art. L. 421-55 à L. 421-57) · formation transport.
 *   - Taxe fixe Y4 (CIBS art. L. 421-49) · 11 € fixe.
 *
 * **Évolution majeure 01/05/2025** · l'exonération régionale Y1 pour
 * véhicules électriques/hydrogène devient **facultative par région**
 * (chaque conseil régional décide de l'application ou non). Les régions
 * qui décident de ne plus exonérer voient leurs tarifs Y1 s'appliquer
 * aux VE/H₂ aussi.
 *
 * **Redevable** · titulaire du certificat d'immatriculation (= bailleur
 * dans le modèle Floty · entreprises utilisatrices jamais redevables).
 */
final readonly class R2025_031_RegistrationCardTaxes implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-031';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return "Taxes liées au certificat d'immatriculation (carte grise)";
    }

    public function description(): string
    {
        return "Trois taxes ponctuelles acquittées lors de l'établissement de la carte grise · taxe régionale Y1 (tarif au cheval fiscal, variable par région), taxe Y2 (formation professionnelle transport routier, pour VUL et camions), taxe fixe Y4 (11 €). Évolution majeure 01/05/2025 · l'exonération régionale Y1 pour véhicules électriques/hydrogène devient facultative par région (chaque conseil régional décide). Redevable · titulaire de la carte grise. Hors périmètre Floty · le bailleur immatricule les véhicules · les entreprises utilisatrices ne sont jamais directement redevables.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2025-05-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-49 (taxe fixe Y4)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2025-05-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-55 à L. 421-57 (taxe Y2)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599043/2025-05-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'ANTS · Taxes sur les cartes grises',
                'url' => 'https://immatriculation.ants.gouv.fr/tout-savoir/taxes-sur-les-cartes-grises',
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
            title: "Taxes liées au certificat d'immatriculation",
            pitch: "Taxes acquittées à chaque délivrance d'une carte grise · taxe régionale (au cheval fiscal), taxe formation transport, taxe fixe.",
            body: "Acquittées par le titulaire de la carte grise · dans le modèle Floty, le bailleur immatricule les véhicules et acquitte ces taxes ponctuelles. Les entreprises utilisatrices ne sont jamais directement redevables. Évolution majeure au 01/05/2025 · l'exonération régionale Y1 pour véhicules électriques/hydrogène devient facultative par région · les régions qui décident de ne plus exonérer voient leurs tarifs Y1 s'appliquer aussi aux VE/H₂. Documentées pour exhaustivité.",
        );
    }
}
