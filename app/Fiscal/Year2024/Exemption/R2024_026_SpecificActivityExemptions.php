<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

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
 * R-2024-026 · Exonérations propres à certaines activités économiques.
 *
 * Regroupe trois exonérations légales prévues par le CIBS (et leurs
 * miroirs polluants à texte identique) :
 *   - Transport public de personnes · CIBS L. 421-130 (CO₂) / L. 421-142 (polluants)
 *   - Activités agricoles ou forestières · CIBS L. 421-131 (CO₂) / L. 421-143 (polluants)
 *     (soumise au plafond de minimis européen)
 *   - Enseignement de la conduite ou du pilotage + compétitions sportives ·
 *     CIBS L. 421-132 (CO₂) / L. 421-144 (polluants)
 *
 * **INACTIVE par défaut V1** · aucune entreprise utilisatrice Floty
 * actuelle n'exerce ces activités (clients = sociétés commerciales
 * standard, hors transport public / agricole / auto-école / compétition).
 * La règle est structurellement câblée pour activation future via
 * seeder/UI si le périmètre métier évolue.
 *
 * Note V1 : tant que {@see PipelineContext} ne porte pas l'activité de
 * l'entreprise utilisatrice, cette règle retourne `notExempt()`. Le
 * critère d'activité spécifique serait évalué côté contrat (V2+).
 */
final readonly class R2024_026_SpecificActivityExemptions implements ExemptionRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-026';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Exonérations propres à certaines activités économiques';
    }

    public function description(): string
    {
        return "Trois exonérations regroupées · transport public de personnes (taxi, VTC, autobus), activités agricoles ou forestières (sous plafond de minimis), enseignement de la conduite ou du pilotage et compétitions sportives. Texte identique pour les deux taxes (les articles L. 421-142/143/144 reprennent mot pour mot L. 421-130/131/132). INACTIVE par défaut · aucun client de l'application n'exerce ces activités.";
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
            // CIBS L. 421-130 · exo CO₂ transport public personnes
            [
                'type' => 'CIBS',
                'article' => 'L. 421-130',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602953/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            // CIBS L. 421-131 · exo CO₂ agricole/forestier (de minimis)
            [
                'type' => 'CIBS',
                'article' => 'L. 421-131',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602951/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            // CIBS L. 421-132 · exo CO₂ enseignement conduite + compétitions
            [
                'type' => 'CIBS',
                'article' => 'L. 421-132',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602949/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            // CIBS L. 421-142 · miroir polluants transport public personnes
            [
                'type' => 'CIBS',
                'article' => 'L. 421-142',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602915/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            // CIBS L. 421-143 · miroir polluants agricole/forestier
            [
                'type' => 'CIBS',
                'article' => 'L. 421-143',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602913/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            // CIBS L. 421-144 · miroir polluants enseignement + compétitions
            [
                'type' => 'CIBS',
                'article' => 'L. 421-144',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602911/2024-06-01',
                'consulted_at' => '2026-05-14',
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
        // Tant que le contexte ne porte pas l'activité de l'entreprise
        // utilisatrice, pas d'évaluation possible. Cas attendu V1.
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
