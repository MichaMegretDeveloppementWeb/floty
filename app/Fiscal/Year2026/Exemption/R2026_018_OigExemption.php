<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2026-018 - "Certain forms of operation" OIG exemption, version
 * 01/01 → 31/08/2026 (before Ordo 2025-1247 art. 4).
 *
 * Pipeline rule (Exemption) INACTIVE by default. ADR-0022:
 * counterpart to {@see R2026_018bis_OigExemption} which covers
 * 01/09-31/12. L. 421-126 rewritten by Ordonnance n° 2025-1247 du
 * 17/12/2025 art. 4 effective 01/09/2026: the reference to CGI
 * art. 261 is replaced by a reference to articles L. 213-104 to
 * L. 213-120 CIBS (codified law overhaul, VAT recodification).
 *
 * Mechanics: if the user company is an OIG within the meaning of CGI
 * art. 261, 7° (association loi 1901, foundation, etc.) AND the
 * vehicle is exclusively assigned to its non-profit activity, the
 * exemption applies to both taxes (CO₂ via L. 421-126 and pollutants
 * via the L. 421-138 mirror).
 *
 * Inactive by default in V1: no current Floty user company is OIG.
 */
final readonly class R2026_018_OigExemption implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2026-018';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 8, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return "Exonération organisme d'intérêt général (version 01/01 → 31/08/2026)";
    }

    public function description(): string
    {
        return 'OIG (CGI art. 261, 7°) : exonération CO₂ et polluants. Texte identique pour les deux taxes (L. 421-138 reprend L. 421-126 mot pour mot). INACTIVE par défaut. Version 01/01-31/08/2026 · texte L. 421-126/138 v 31/12/2023 → 01/09/2026 (référence CGI 261 avant refonte).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 18;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-126',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602965/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-138',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602927/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CGI',
                'article' => '261',
                'paragraph' => '7',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051764761/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    public function isActive(): bool
    {
        return false;
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        return ExemptionVerdict::notExempt();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::ExonerationInactive,
            title: 'Exonération organisme d\'intérêt général (avant 01/09/2026)',
            pitch: 'Véhicules détenus par une association 1901, fondation, etc., affectés exclusivement à l\'activité non lucrative.',
            body: "Modélisée en base mais inactive par défaut · aucune entreprise utilisatrice de la flotte n'est un organisme d'intérêt général. Version 01/01-31/08/2026 · texte référençant l'art. 261 du CGI (avant recodification par Ordo 2025-1247). La période 01/09-31/12/2026 est portée par R-2026-018-bis · cascade applicative strictement identique, seules les références codifiées changent.",
        );
    }
}
