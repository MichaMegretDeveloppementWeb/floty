<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Exemption;

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
 * R-2025-018 - "Certain forms of operation" exemption: vehicles
 * assigned to VAT-exempt operations (CIBS L. 421-126 / L. 421-138),
 * strict reproduction of R-2024-018 INACTIVE by default.
 *
 * Official legal title: L. 421-126 "Exonération pour certaines formes
 * d'exploitation". Targets vehicles assigned to the VAT-exempt
 * operations mentioned in 1° of 7 of article 261 CGI (non-profit
 * bodies, commonly "OIG" / "organismes d'intérêt général").
 *
 * Mechanics: if the user company is a non-profit body within the
 * meaning of CGI art. 261, 7° (association loi 1901, foundation, etc.)
 * AND the vehicle is exclusively assigned to its non-profit activity,
 * the exemption applies to both taxes (CO₂ and pollutants via the
 * mirror articles L. 421-126 / L. 421-138).
 *
 * Terminology note: the short label "OIG" is a common shortcut for
 * "organisme d'intérêt général". The legal text refers to "opérations
 * mentionnées au 1° du 7 de l'article 261 du CGI".
 *
 * Inactive by default in V1: no current Floty user company is OIG.
 * Structurally activatable via seeder/UI.
 */
final readonly class R2025_018_OigExemption implements ExemptionRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2025-018';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return "Exonération organisme d'intérêt général";
    }

    public function description(): string
    {
        return 'OIG (CGI art. 261, 7°) : exonération CO₂ et polluants. Texte identique pour les deux taxes (L. 421-138 reprend L. 421-126 mot pour mot). INACTIVE par défaut. Reconduction stricte 2024.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602965/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-138',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602927/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CGI',
                'article' => '261',
                'paragraph' => '7',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051764761/2025-01-01',
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
        return ExemptionVerdict::notExempt();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::ExonerationInactive,
            title: 'Exonération organisme d\'intérêt général',
            pitch: 'Véhicules détenus par une association 1901, fondation, etc., affectés exclusivement à l\'activité non lucrative.',
            body: "Modélisée en base mais inactive par défaut : aucune entreprise utilisatrice de la flotte n'est un organisme d'intérêt général. Activable manuellement si le périmètre évolue.",
        );
    }
}
