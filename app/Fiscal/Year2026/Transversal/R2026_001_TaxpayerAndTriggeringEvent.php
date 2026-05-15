<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-001 · Redevable et fait générateur · version unique pleine année
 * 2026.
 *
 * **Règle documentaire-only** · pas de scission ADR-0022 en 2026 ·
 * L. 421-94, L. 421-95, L. 421-98 et L. 421-99 sont stables depuis le
 * 01/03/2025 (rédaction issue de LF 2025 art. 28 · LOI n°2025-127 du
 * 14/02/2025). Audit Chrome live 15/05/2026 confirme · aucune
 * modification par LF 2026 ni par Ordo 2025-1247 du 17/12/2025.
 *
 * **Reconduction stricte 2025 → 2026** · doctrine identique à
 * R-2025-001-bis · trois conditions cumulatives explicites de
 * l'affectation à des fins économiques (détention par entreprise
 * immat. France · prise en charge de frais d'utilisation par entreprise ·
 * circulation pour activité économique ≥ 1 mois/an).
 */
final readonly class R2026_001_TaxpayerAndTriggeringEvent implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-001';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Redevable et fait générateur';
    }

    public function description(): string
    {
        return "Cadre architectural du redevable et du fait générateur en 2026 · doctrine stable depuis LF 2025 art. 28 (effet 01/03/2025). Les articles L. 421-94, L. 421-95, L. 421-98 et L. 421-99 n'ont pas été modifiés par la LF 2026 ni par l'Ordonnance n° 2025-1247 du 17/12/2025. Trois conditions cumulatives explicites d'affectation à des fins économiques (détention par entreprise immatriculée en France · prise en charge de frais d'utilisation par entreprise · circulation pour activité économique ≥ 1 mois/an).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
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
                'article' => 'L. 421-94',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603051/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-95',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214924/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-98',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214920/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-99',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603043/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-15',
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
            title: 'Redevable et fait générateur (2026)',
            pitch: "L'entreprise utilisatrice effective est redevable, dès lors qu'elle dispose du véhicule pour ses besoins économiques. Doctrine stable depuis LF 2025 art. 28 (effet 01/03/2025), reconduite sans modification en 2026.",
            body: "Trois conditions cumulatives explicites définissent l'affectation à des fins économiques · 1) le véhicule est détenu par une entreprise immatriculée en France, 2) une entreprise prend en charge tout ou partie des frais d'utilisation, 3) le véhicule circule pour les besoins d'une activité économique pendant au moins un mois sur l'année civile. Le fait générateur naît au début de la période d'affectation et est exigible mensuellement. Pas de modification 2026 vs version 2025 stabilisée.",
        );
    }
}
