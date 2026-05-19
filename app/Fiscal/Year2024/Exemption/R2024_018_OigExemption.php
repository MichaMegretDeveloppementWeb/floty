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
 * R-2024-018 · public-interest organisation exemption (CIBS L. 421-126
 * / L. 421-138).
 *
 * If the using company is a public-interest organisation (CGI art.
 * 261, 7°) AND the vehicle is exclusively assigned to its
 * non-profit activity, both taxes are waived. Flag: `companies.is_oig`.
 *
 * Inactive by default in V1: no current Floty using company is an
 * OIG. The rule is wired for future activation via the seeder / UI.
 *
 * As long as the {@see PipelineContext} does not carry the pair's
 * `Company`, this rule returns `notExempt()`. The exclusive-assignment
 * criterion will be evaluated at contract level in V2, not on the VFC
 * (which does not carry per-company usage semantics).
 */
final readonly class R2024_018_OigExemption implements ExemptionRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-018';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return "Exonération organisme d'intérêt général";
    }

    public function description(): string
    {
        return 'OIG (CGI art. 261, 7°) : exonération CO₂ et polluants. Texte identique pour les deux taxes (L. 421-138 reprend L. 421-126 mot pour mot). INACTIVE par défaut.';
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
            // CIBS L. 421-126 · CO₂ tax exemption for vehicles
            // assigned to VAT-exempt operations under CGI art. 261, 9°
            // of 4 and 7.
            [
                'type' => 'CIBS',
                'article' => 'L. 421-126',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602965/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            // L. 421-138 mirrors L. 421-126 word-for-word for the
            // pollutants tax. Required for fiscal traceability on both
            // taxes.
            [
                'type' => 'CIBS',
                'article' => 'L. 421-138',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602927/2024-06-01',
                'consulted_at' => '2026-05-13',
            ],
            // Source defining the OIG scope. CGI art. 261, 7° defines
            // the public-interest organisations exempt from VAT to
            // which CIBS L. 421-126 / L. 421-138 refer.
            [
                'type' => 'CGI',
                'article' => '261',
                'paragraph' => '7',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051764761/2024-06-01',
                'consulted_at' => '2026-05-13',
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
        // Cannot evaluate while the context does not carry the company
        // (expected V1 state).
        return ExemptionVerdict::notExempt();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::ExonerationInactive,
            title: 'Exonération organisme d’intérêt général',
            pitch: 'Véhicules détenus par une association 1901, fondation, etc., affectés exclusivement à l’activité non lucrative.',
            body: "Modélisée en base mais inactive par défaut : aucune entreprise utilisatrice de la flotte n'est un organisme d'intérêt général. Activable manuellement si le périmètre évolue.",
        );
    }
}
