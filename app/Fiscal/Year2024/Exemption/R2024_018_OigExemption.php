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
 * R-2024-018 - Exonération organisme d'intérêt général (CIBS L. 421-126
 * / L. 421-138).
 *
 * Si l'entreprise utilisatrice est un organisme d'intérêt général
 * (CGI art. 261, 7°) ET que le véhicule est exclusivement affecté à son
 * activité non lucrative, l'exonération s'applique sur les deux taxes.
 * Flag : `companies.is_oig`.
 *
 * **Inactif par défaut V1** : aucune entreprise utilisatrice Floty
 * actuelle n'est OIG. La règle est structurellement câblée pour
 * activation future via seeder/UI.
 *
 * Note V1 : tant que le {@see PipelineContext} ne porte pas la
 * `Company` du couple, cette règle retourne `notExempt()`. Le critère
 * d'affectation exclusive sera évalué côté contrat (V2) - pas sur la
 * VFC, qui ne porte pas la sémantique d'usage par entreprise.
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
            // CIBS L. 421-126 · exonération taxe CO₂ pour les véhicules
            // affectés aux opérations exonérées de TVA mentionnées au
            // 9° du 4 et au 7 de l'article 261 du CGI.
            [
                'type' => 'CIBS',
                'article' => 'L. 421-126',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602965/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            // Phase 13 D5.11 (audit sémantique) · jumeau strict de
            // L. 421-126 pour la taxe polluants (texte identique
            // mot pour mot · cf. description PHP). Indispensable pour
            // la traçabilité fiscale sur les 2 taxes.
            [
                'type' => 'CIBS',
                'article' => 'L. 421-138',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602927/2024-06-01',
                'consulted_at' => '2026-05-13',
            ],
            // Phase 13 D5.11 (audit sémantique) · texte source du
            // périmètre OIG · le 7 de l'art. 261 CGI définit les
            // organismes d'utilité générale exonérés de TVA, auxquels
            // les exonérations CIBS L. 421-126 / L. 421-138 renvoient.
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
        // Tant que le contexte ne porte pas la company, pas
        // d'évaluation possible. Cas attendu V1.
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
