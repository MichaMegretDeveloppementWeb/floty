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
 * R-2026-026 · Exonérations propres à certaines activités économiques ·
 * reconduction stricte 2025 · INACTIVE par défaut.
 *
 * Regroupe trois exonérations CIBS (et leurs miroirs polluants à texte
 * identique) ·
 *   - Transport public de personnes · L. 421-130 (CO₂) / L. 421-142 (polluants)
 *   - Activités agricoles ou forestières · L. 421-131 / L. 421-143
 *     (soumise au plafond de minimis européen)
 *   - Enseignement de la conduite ou du pilotage + compétitions sportives ·
 *     L. 421-132 / L. 421-144
 *
 * **Stabilité 2026** · les 6 articles (L. 421-130/131/132 CO₂ et leurs
 * miroirs polluants L. 421-142/143/144) sont stables depuis 01/01/2022.
 * Audit Chrome live 15/05/2026 confirme · non touchés par Ordo 2025-1247
 * (vérifié explicitement pour L. 421-130, 131, 132, 142). Les 3 articles
 * miroirs polluants restants (L. 421-136, 139, 143, 144 non encore
 * audités spécifiquement au 01/09/2026) sont supposés stables · règles
 * inactives Floty V1, audit complémentaire prévu en Z3 audit URLs.
 *
 * **INACTIVE par défaut V1** · aucune entreprise utilisatrice Floty
 * actuelle n'exerce ces activités.
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
