<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2026-031-bis - Registration certificate taxes, version 01/03 →
 * 31/12/2026 (creation of L. 421-54-1 by LF 2026 art. 60, IDF
 * surcharge up to +13 €).
 *
 * Out-of-scope fiscal rule, counterpart to
 * {@see R2026_031_RegistrationCardTaxes} which covers 01/01-28/02/2026.
 *
 * Material evolution 01/03/2026: creation of CIBS article L. 421-54-1
 * by LOI n° 2026-103 du 19 février 2026 art. 60 (effective 01/03/2026
 * by IV). The regional Y1 tariff is, on STIF deliberation
 * (Île-de-France Mobilités, L. 1241-1 of the transport code), raised
 * up to 13 € for registration certificates issued in Île-de-France.
 * The surcharge does not enter the L. 421-42 cap. The L. 421-45 to
 * L. 421-54 exemptions and reduced tariffs apply to the surcharge.
 *
 * Marked inactive: real fiscal rule but out of Floty calculation
 * scope.
 */
final readonly class R2026_031bis_RegistrationCardTaxes implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2026-031-bis';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 3, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return "Taxes liées au certificat d'immatriculation (version 01/03 → 31/12/2026, majoration IDF L. 421-54-1)";
    }

    public function description(): string
    {
        return "Trois taxes ponctuelles acquittées lors de l'établissement de la carte grise · taxe régionale Y1, taxe Y2 (formation transport), taxe fixe Y4 (11 €). Évolution matérielle au 01/03/2026 · création de l'article CIBS L. 421-54-1 par LF 2026 art. 60 (LOI n° 2026-103 du 19/02/2026) · le tarif régional Y1 peut être majoré, sur délibération du STIF (Île-de-France Mobilités), dans la limite de 13 € pour les cartes grises délivrées en Île-de-France. Les exonérations et tarifs réduits L. 421-45 à L. 421-54 s'appliquent à cette majoration. Hors périmètre de l'application · le bailleur acquitte ces taxes, pas l'entreprise utilisatrice.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 31;
    }

    public function isActive(): bool
    {
        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-41 à L. 421-54-1 (section · v 01/03/2026)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2026-03-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2026 art. 60',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000053508155',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'ANTS · Taxes sur les cartes grises',
                'url' => 'https://immatriculation.ants.gouv.fr/tout-savoir/taxes-sur-les-cartes-grises',
                'consulted_at' => '2026-05-15',
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
            title: "Taxes liées au certificat d'immatriculation (majoration IDF L. 421-54-1)",
            pitch: "Période 01/03-31/12/2026 · création par LF 2026 art. 60 d'une majoration régionale Y1 jusqu'à +13 € pour l'Île-de-France, sur délibération du STIF (L. 1241-1 du code des transports).",
            body: "Mêmes taxes Y1/Y2/Y4 qu'avant le 01/03/2026, plus une majoration nouvelle réservée à l'Île-de-France · le STIF peut décider d'ajouter jusqu'à 13 € à la Y1 régionale francilienne pour financer les transports en commun. Les exonérations VE/H₂ qui s'appliquent à la Y1 régionale s'appliquent aussi automatiquement à la majoration. Hors périmètre de l'application · le bailleur paie ces taxes lors de l'immatriculation.",
            example: "Une carte grise délivrée le 15 avril 2026 pour un véhicule de 7 CV immatriculé à Paris · Y1 régionale IDF (~340 € pour 7 CV à 49 €/CV) + majoration STIF (jusqu'à +13 €) = jusqu'à 353 € côté Y1 régionale, vs ~340 € en février 2026.",
        );
    }
}
