<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

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
 * R-2024-003 · commercial half-up rounding (CIBS L. 131-1).
 *
 * BOFiP semantics: "the total amount owed by each taxpayer is rounded
 * to the nearest euro without intermediate rounding". The per-taxpayer
 * rounding therefore happens in
 * {@see FleetFiscalAggregator::companyAnnualTax()}, which sums
 * `co2DueRaw` + `pollutantsDueRaw` across the company's vehicles and
 * rounds once.
 *
 * This rule class is kept as a marker in the pipeline (appearing in
 * `appliedRuleCodes` of the snapshot and on the Règles page). It does
 * not change amounts: the per-pair rounding used for row display
 * (PDF, planning drawer) is applied by the pipeline itself in
 * `buildResult()`.
 */
final readonly class R2024_003_FinalRounding implements TransversalRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-003';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return "Méthode d'arrondi half-up commercial";
    }

    public function description(): string
    {
        return 'Arrondi half-up au centime sur le montant total final par couple véhicule × entreprise (round half-up à 2 décimales). Les calculs intermédiaires conservent toute leur précision.';
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
            // CIBS L. 131-1 · Book I pivot article stating that CIBS
            // tax bases are rounded "under the conditions of CGI art.
            // 1649 undecies".
            [
                'type' => 'CIBS',
                'article' => 'L. 131-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044604185/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            // CGI 1649 undecies · target of the L. 131-1 reference,
            // arithmetic half-up principle ("a fraction equal to 0.50
            // counts for 1"). Note: 1649 undecies rounds bases to the
            // euro in the general sense; Floty's per-pair cent-level
            // rounding derives by analogy of the half-up rule,
            // formalised by BOFiP practice for CIBS vehicle taxes
            // (BOI-AIS-MOB-10-30-10).
            [
                'type' => 'CGI',
                'article' => '1649 undecies',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006306979/2024-06-01',
                'consulted_at' => '2026-05-13',
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
            title: 'Méthode d’arrondi',
            pitch: 'Arrondi au centime, half-up commercial, appliqué au montant total final de chaque ligne fiscale.',
            body: "Les calculs intermédiaires (tarif plein, prorata) sont conservés en haute précision. Seul le montant final par ligne (taxe CO₂ d'un couple, taxe polluants d'un couple) est arrondi au centime supérieur lorsque le demi-centime est atteint (commercial half-up).",
        );
    }
}
