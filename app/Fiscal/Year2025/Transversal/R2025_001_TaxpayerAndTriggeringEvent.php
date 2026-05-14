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
 * R-2025-001 · Redevable et fait générateur · **version 01/01 →
 * 28/02/2025** (avant LF 2025 art. 28).
 *
 * **Règle documentaire-only** · ADR-0022 · une période légale distincte =
 * une règle fiscale Floty distincte. L. 421-95 et L. 421-98 ont été
 * réécrits par la LOI n°2025-127 du 14/02/2025 (LF 2025) à effet du
 * 01/03/2025. La règle R-2025-001 couvre la période **avant** cette
 * modification. La période **après** est portée par
 * {@see R2025_001bis_TaxpayerAndTriggeringEvent}.
 *
 * Cadre architectural · l'entreprise utilisatrice est redevable au titre
 * de l'affectation à des fins économiques (L. 421-95) dans sa version
 * issue de l'ordonnance n°2021-1843 + LF 2022/2023.
 */
final readonly class R2025_001_TaxpayerAndTriggeringEvent implements InformativeRule
{
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-001';
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
        return CarbonImmutable::create(2025, 2, 28, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Redevable et fait générateur (version 01/01 → 28/02/2025)';
    }

    public function description(): string
    {
        return "Définit le périmètre du fait générateur (affectation d'un véhicule à des fins économiques · CIBS L. 421-95) et la qualification de l'entreprise affectataire redevable (CIBS L. 421-98) dans leur version antérieure à LF 2025 art. 28 (01/01/2022 → 28/02/2025). La période suivante (01/03 → 31/12/2025) est portée par R-2025-001-bis.";
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
                'article' => 'L. 421-95',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214924/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-98',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214920/2025-01-01',
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
            title: 'Redevable et fait générateur (avant LF 2025 art. 28)',
            pitch: "Période 01/01 → 28/02/2025 · le redevable est l'entreprise utilisatrice effective du véhicule. Le fait générateur est l'affectation du véhicule à ses besoins économiques.",
            body: "Cette règle couvre la période où L. 421-95 et L. 421-98 étaient encore dans leur version antérieure à LF 2025 art. 28. La version applicable au 01/03/2025 et au-delà est portée par R-2025-001-bis. Sur la période 01/01-28/02/2025 · si le véhicule est en stock chez le bailleur, personne n'est redevable (exonération loueur R-020). S'il est attribué à une entreprise utilisatrice, c'est elle qui devient redevable au prorata.",
        );
    }
}
