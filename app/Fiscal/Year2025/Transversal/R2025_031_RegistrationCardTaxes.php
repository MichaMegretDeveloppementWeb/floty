<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2025-031 - Registration certificate taxes, version 01/01 →
 * 30/04/2025 (nationally mandatory Y1 exemption for EV/H₂).
 *
 * Out-of-scope fiscal rule: three one-shot taxes paid when a
 * registration certificate is issued: regional tax Y1 (CIBS L. 421-41
 * to L. 421-54-1), tax Y2 (L. 421-55 to L. 421-57, transport
 * training), fixed tax Y4 (L. 421-49, 11 €).
 *
 * On 01/01-30/04/2025: regional Y1 exemption for electric/hydrogen
 * vehicles is nationally mandatory. The period 01/05-31/12/2025
 * (optional exemption per region) is carried by
 * {@see R2025_031bis_RegistrationCardTaxes}.
 *
 * Marked inactive: real fiscal rule but out of Floty calculation
 * scope (renter pays, not the user company).
 */
final readonly class R2025_031_RegistrationCardTaxes implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2025-031';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 4, 30, 23, 59, 59);
    }

    public function name(): string
    {
        return "Taxes liées au certificat d'immatriculation (version 01/01 → 30/04/2025)";
    }

    public function description(): string
    {
        return "Trois taxes ponctuelles acquittées lors de l'établissement de la carte grise · taxe régionale Y1, taxe Y2 (formation transport routier, VUL et camions), taxe fixe Y4 (11 €). Sur la période 01/01-30/04/2025, l'exonération régionale Y1 pour véhicules électriques/hydrogène est nationale obligatoire. Hors périmètre de l'application · le bailleur immatricule les véhicules et acquitte ces taxes · les entreprises utilisatrices ne sont jamais directement redevables. La période 01/05-31/12/2025 (exonération Y1 facultative par région) est portée par R-2025-031-bis.";
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
                'article' => 'L. 421-41 à L. 421-54-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2025-01-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-49',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2025-01-01/',
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
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::TaxeConnexe,
            title: "Taxes liées au certificat d'immatriculation (avant évolution 01/05/2025)",
            pitch: "Période 01/01-30/04/2025 · taxes payées à la délivrance d'une carte grise · taxe régionale Y1, taxe formation transport Y2, taxe fixe Y4.",
            body: "Acquittées par le titulaire de la carte grise · le bailleur (société de location) immatricule les véhicules et acquitte ces taxes ponctuelles. Les entreprises utilisatrices ne sont jamais directement redevables. Sur cette période, l'exonération régionale Y1 pour véhicules électriques/hydrogène est nationale obligatoire. L'application ne calcule pas ces taxes · documentées pour exhaustivité.",
        );
    }
}
