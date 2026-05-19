<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-027 · mileage-reimbursement weighting coefficient and tax
 * reduction.
 *
 * CIBS sub-paragraph dedicated to vehicles assigned to economic
 * purposes through company mileage reimbursement (L. 421-95 2°):
 *   - L. 421-109 · sub-paragraph header.
 *   - L. 421-110 · weighting coefficient applied to the prorata
 *     factor by reimbursed mileage (0 / 25 / 50 / 75 / 100 %).
 *   - L. 421-111 · 15 000 € reduction on the company's cumulative
 *     taxes (under the European de minimis ceiling).
 *
 * Inactive by default in V1. Floty covers fleet vehicles held by the
 * renter and made available to using companies, not employee /
 * director personal vehicles giving rise to mileage reimbursement.
 * L. 421-109 explicitly scopes the sub-paragraph to L. 421-95 2°
 * vehicles, outside Floty's usage by architectural construction.
 *
 * Documented for fiscal completeness: if Floty ever extends to
 * mileage reimbursement, the coefficient + reduction mechanics will
 * be coded and the rule will become active.
 */
final readonly class R2024_027_MileageReimbursementCoefficient implements TransversalRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-027';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Coefficient pondérateur et minoration des frais kilométriques';
    }

    public function description(): string
    {
        return "Lorsqu'une entreprise prend en charge les frais kilométriques d'un véhicule détenu par un salarié ou un dirigeant, le facteur de prorata est modulé par un coefficient pondérateur (0/25/50/75/100 %) selon le nombre de kilomètres remboursés (CIBS L. 421-110). Le montant cumulé des taxes pour ces véhicules est par ailleurs réduit d'une minoration de 15 000 € par entreprise et par an (CIBS L. 421-111), sous plafond de minimis européen. INACTIVE par défaut · l'application ne couvre pas les véhicules salariés/dirigeants.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 27;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-109',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000046196655/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-110',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000046196651/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-111',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603007/2024-06-01',
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

    public function apply(PipelineContext $context): PipelineContext
    {
        // Tant que le contexte ne porte pas la sémantique « véhicule
        // salarié/dirigeant » et le nombre de kilomètres remboursés,
        // pas d'application possible. Cas attendu V1 (Floty n'a pas
        // ces véhicules par architecture).
        return $context;
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::ExonerationInactive,
            title: 'Coefficient pondérateur et minoration frais kilométriques',
            pitch: 'Mécanique fiscale spécifique aux véhicules personnels de salariés ou dirigeants dont l\'entreprise prend en charge les frais kilométriques.',
            body: "l'application suit des véhicules de flotte détenus par la société de location et mis à disposition d'entreprises utilisatrices · pas des véhicules personnels de salariés. Cette mécanique (coefficient pondérateur 0/25/50/75/100 % et minoration de 15 000 € par entreprise) est documentée pour exhaustivité mais reste inactive dans l'application.",
        );
    }
}
