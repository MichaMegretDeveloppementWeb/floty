<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2025-001-bis · Redevable et fait générateur · **version 01/03 →
 * 31/12/2025** (après LF 2025 art. 28).
 *
 * **Règle documentaire-only** · pendant de {@see R2025_001_TaxpayerAndTriggeringEvent}
 * qui couvre 01/01-28/02. ADR-0022 · une période légale distincte = une
 * règle fiscale Floty distincte.
 *
 * **Modifications LF 2025 art. 28** · L. 421-95 et L. 421-98 ont été
 * réécrits par la LOI n°2025-127 du 14/02/2025 à effet du 01/03/2025.
 * Modifications de structure rédactionnelle (trois conditions explicites
 * de l'affectation à des fins économiques, clarification cost-sharing,
 * minimum 1 mois annuel) · pas d'impact pratique sur la doctrine du
 * redevable. L. 421-99 inchangé depuis 01/01/2022.
 */
final readonly class R2025_001bis_TaxpayerAndTriggeringEvent implements InformativeRule
{
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-001-bis';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 3, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Redevable et fait générateur (version 01/03 → 31/12/2025, modif LF 2025 art. 28)';
    }

    public function description(): string
    {
        return "Cadre architectural l'application depuis LF 2025 art. 28. L. 421-95 et L. 421-98 ont été réécrits par la LOI n°2025-127 du 14/02/2025 à effet du 01/03/2025 · clarification des trois conditions explicites d'affectation à des fins économiques (détention par entreprise immat. France · prise en charge de frais d'utilisation par entreprise · circulation pour activité économique ≥ 1 mois/an) sans changement de doctrine. L. 421-99 inchangé. La période précédente (01/01 → 28/02/2025) est portée par R-2025-001.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        // Doit suivre R-2025-001 dans l'ordre d'affichage.
        return 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-95',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214924/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-98',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214920/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-99',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603043/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LOI n°2025-127 du 14 février 2025 art. 28 (réécriture L. 421-95/98 à effet 01/03/2025)',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000051168007',
                'consulted_at' => '2026-05-14',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreImplicite,
            title: 'Redevable et fait générateur (depuis LF 2025 art. 28)',
            pitch: 'Période 01/03 → 31/12/2025 · même doctrine du redevable (entreprise utilisatrice effective) avec rédaction modernisée par LF 2025 art. 28.',
            body: "Cette règle couvre la période où L. 421-95 et L. 421-98 sont dans leur version réécrite par la LOI n°2025-127 du 14/02/2025 (LF 2025), à effet du 01/03/2025. La définition de l'affectation à des fins économiques est désormais structurée en trois conditions cumulatives explicites (détention par entreprise immatriculée en France · prise en charge de frais d'utilisation par entreprise · circulation pour activité économique pendant au moins 1 mois/an). Sans changement de doctrine pratique vs version antérieure (R-2025-001).",
        );
    }
}
