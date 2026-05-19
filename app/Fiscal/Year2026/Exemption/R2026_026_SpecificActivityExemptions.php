<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-026 - Exemptions specific to certain economic activities,
 * strict reproduction of R-2025-026, INACTIVE by default.
 *
 * Groups three CIBS exemptions (and their pollutant mirrors with
 * identical text):
 *   - Public passenger transport: L. 421-130 (CO₂) / L. 421-142 (pollutants)
 *   - Agricultural or forestry activities: L. 421-131 / L. 421-143
 *     (subject to the European de minimis ceiling)
 *   - Driving / piloting instruction + sports competitions:
 *     L. 421-132 / L. 421-144
 *
 * 2026 stability: the 6 articles (L. 421-130/131/132 CO₂ and their
 * pollutant mirrors L. 421-142/143/144) are stable since 01/01/2022
 * and not touched by Ordo 2025-1247.
 *
 * Inactive by default in V1: no current Floty user company performs
 * these activities.
 */
final readonly class R2026_026_SpecificActivityExemptions implements ExemptionRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2026-026';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Exonérations propres à certaines activités économiques';
    }

    public function description(): string
    {
        return "Trois exonérations regroupées · transport public de personnes (taxi, VTC, autobus), activités agricoles ou forestières (sous plafond de minimis), enseignement de la conduite ou du pilotage et compétitions sportives. Texte identique pour les deux taxes (les articles L. 421-142/143/144 reprennent mot pour mot L. 421-130/131/132). INACTIVE par défaut · aucun client de l'application n'exerce ces activités. Reconduction stricte 2025.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 26;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-130',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602953/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-131',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602951/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-132',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602949/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-142',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602915/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-143',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602913/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-144',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602911/2026-01-01',
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
            title: 'Exonérations propres à certaines activités économiques',
            pitch: 'Exonérations totales pour les véhicules affectés au transport public de personnes, aux activités agricoles ou forestières, à l\'enseignement de la conduite ou aux compétitions sportives.',
            body: "Modélisée en base mais inactive par défaut · les entreprises utilisatrices de la flotte de l'application exercent toutes des activités commerciales standard, sans exonération à ce titre. L'exonération agricole est par ailleurs soumise au plafond de minimis européen. Activable manuellement si le périmètre métier évolue.",
        );
    }
}
