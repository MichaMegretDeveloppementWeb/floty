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
 * R-2026-031-bis · Taxes liées au certificat d'immatriculation ·
 * **version 01/03 → 31/12/2026** (création L. 421-54-1 par LF 2026
 * art. 60 · majoration IDF jusqu'à +13 €).
 *
 * **Règle fiscale hors périmètre de l'application** · pendant de
 * {@see R2026_031_RegistrationCardTaxes} qui couvre 01/01-28/02/2026.
 *
 * **Évolution matérielle 01/03/2026** · création de l'article CIBS
 * **L. 421-54-1** par la LOI n° 2026-103 du 19 février 2026 art. 60
 * (effet 01/03/2026 par IV) · le tarif régional Y1 est, **sur
 * délibération du STIF (Île-de-France Mobilités, L. 1241-1 du code des
 * transports)**, majoré dans la limite de **13 €** pour les certificats
 * d'immatriculation délivrés en Île-de-France. La majoration n'entre
 * pas dans le plafonnement L. 421-42. Les exonérations et tarifs
 * réduits L. 421-45 à L. 421-54 s'appliquent à la majoration.
 *
 * **Audit Chrome live 15/05/2026** · L. 421-54-1 v 01/03/2026
 * (URL section LEGISCTA000044599003/2026-03-01) confirmé · « Version
 * en vigueur depuis le 01/03/2026 · Création LOI n°2026-103 du 19
 * février 2026 - art. 60 (V) ». IV de l'art. 60 fixe l'entrée en
 * vigueur au 01/03/2026.
 *
 * **Note bilan Z0** · le bilan législatif annonçait « +14 € IDF mars
 * 2026 » · l'audit Chrome live confirme **+13 € maximum** (correction
 * mineure · pas d'impact sur la doctrine).
 *
 * **Marquée inactive** · règle fiscale réelle mais hors périmètre de
 * calcul de l'application.
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
                'reference' => 'LOI n° 2026-103 du 19 février 2026 (LF 2026) art. 60 · création L. 421-54-1 · majoration IDF jusqu\'à +13 € (effet 01/03/2026 par IV)',
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
            tab: RuleTab::Connexe,
            section: RuleSection::TaxeConnexe,
            title: "Taxes liées au certificat d'immatriculation (majoration IDF L. 421-54-1)",
            pitch: "Période 01/03-31/12/2026 · création par LF 2026 art. 60 d'une majoration régionale Y1 jusqu'à +13 € pour l'Île-de-France, sur délibération du STIF (L. 1241-1 du code des transports).",
            body: "Mêmes taxes Y1/Y2/Y4 qu'avant le 01/03/2026, plus une majoration nouvelle réservée à l'Île-de-France · le STIF peut décider d'ajouter jusqu'à 13 € à la Y1 régionale francilienne pour financer les transports en commun. Les exonérations VE/H₂ qui s'appliquent à la Y1 régionale s'appliquent aussi automatiquement à la majoration. Hors périmètre de l'application · le bailleur paie ces taxes lors de l'immatriculation.",
            example: "Une carte grise délivrée le 15 avril 2026 pour un véhicule de 7 CV immatriculé à Paris · Y1 régionale IDF (~340 € pour 7 CV à 49 €/CV) + majoration STIF (jusqu'à +13 €) = jusqu'à 353 € côté Y1 régionale, vs ~340 € en février 2026.",
        );
    }
}
