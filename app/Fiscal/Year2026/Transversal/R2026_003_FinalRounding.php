<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * R-2026-003 · Arrondi half-up commercial · reconduction stricte de
 * la mécanique 2024-2025, textes CIBS L. 131-1 et CGI 1649 undecies
 * stables (aucune modification ni LF 2026 ni Ordo 2025-1247).
 *
 * **Sémantique BOFiP** · « le montant total à payer par chaque
 * redevable est arrondi à l'euro le plus proche, sans arrondi
 * intermédiaire ». L'arrondi par redevable s'opère dans
 * {@see FleetFiscalAggregator::companyAnnualTax()}.
 *
 * Cette classe règle est conservée comme **marqueur** dans le pipeline
 * (apparaît dans `appliedRuleCodes` du snapshot). Elle ne modifie pas
 * les montants · l'arrondi par couple (utile pour l'affichage par
 * ligne du PDF / drawer planning) est appliqué par le pipeline
 * lui-même dans `buildResult()`.
 *
 * **Base légale** · audit Chrome live 15/05/2026 ·
 * - CIBS art. L. 131-1 · stable depuis 01/01/2022.
 * - CGI art. 1649 undecies · stable.
 */
final readonly class R2026_003_FinalRounding implements TransversalRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-003';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return "Méthode d'arrondi half-up commercial";
    }

    public function description(): string
    {
        return 'Arrondi half-up au centime sur le montant total final par couple véhicule × entreprise (round half-up à 2 décimales). Les calculs intermédiaires conservent toute leur précision. Reconduction stricte 2025 (textes CIBS L. 131-1 et CGI 1649 undecies inchangés).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 3;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 131-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044604185/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CGI',
                'article' => '1649 undecies',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006306979/2026-01-01',
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

    public function apply(PipelineContext $context): PipelineContext
    {
        return $context->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreImplicite,
            title: 'Méthode d\'arrondi',
            pitch: 'Arrondi au centime, half-up commercial, appliqué au montant total final de chaque ligne fiscale.',
            body: "Les calculs intermédiaires (tarif plein, prorata) sont conservés en haute précision. Seul le montant final par ligne (taxe CO₂ d'un couple, taxe polluants d'un couple) est arrondi au centime supérieur lorsque le demi-centime est atteint (commercial half-up). Aucun changement vs 2025.",
        );
    }
}
