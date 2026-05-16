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
 * R-2025-031-bis · Taxes liées au certificat d'immatriculation ·
 * **version 01/05 → 31/12/2025** (exonération Y1 électrique/hydrogène
 * facultative par région).
 *
 * **Règle fiscale hors périmètre de l'application** · pendant de
 * {@see R2025_031_RegistrationCardTaxes} qui couvre 01/01-30/04.
 *
 * **Évolution matérielle 01/05/2025** · l'exonération régionale Y1 pour
 * véhicules électriques/hydrogène devient **facultative par région**
 * (chaque conseil régional décide de l'application ou non). Les régions
 * qui décident de ne plus exonérer voient leurs tarifs Y1 s'appliquer
 * aussi aux VE/H₂.
 *
 * **Marquée inactive** · règle fiscale réelle mais hors périmètre de
 * calcul de l'application.
 */
final readonly class R2025_031bis_RegistrationCardTaxes implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2025-031-bis';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 5, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return "Taxes liées au certificat d'immatriculation (version 01/05 → 31/12/2025, exonération régionale Y1 facultative)";
    }

    public function description(): string
    {
        return "Trois taxes ponctuelles acquittées lors de l'établissement de la carte grise · taxe régionale Y1, taxe Y2 (formation transport), taxe fixe Y4 (11 €). Évolution matérielle au 01/05/2025 · l'exonération régionale Y1 pour véhicules électriques/hydrogène devient facultative par région. Les régions qui décident de ne plus exonérer voient leurs tarifs Y1 s'appliquer aussi aux VE/H₂. Hors périmètre de l'application · le bailleur acquitte ces taxes, pas l'entreprise utilisatrice.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2025-05-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-49',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2025-05-01/',
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
            title: "Taxes liées au certificat d'immatriculation (exonération régionale Y1 facultative)",
            pitch: "Période 01/05-31/12/2025 · l'exonération régionale Y1 pour véhicules électriques/hydrogène devient facultative par région.",
            body: "Mêmes taxes Y1/Y2/Y4 qu'avant le 01/05/2025, mais chaque conseil régional décide désormais de maintenir ou non l'exonération Y1 pour VE/H₂. Conséquence concrète · un VE immatriculé dans une région qui a supprimé l'exonération pourra payer la Y1 régionale (~250-600 € selon CV) là où il en était exonéré nationalement avant le 01/05/2025. Hors périmètre de l'application · le bailleur paie ces taxes lors de l'immatriculation.",
        );
    }
}
