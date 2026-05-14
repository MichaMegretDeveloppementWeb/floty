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
 * R-2024-003 - Arrondi half-up commercial (CIBS L. 131-1).
 *
 * **Sémantique BOFiP** : « le montant total à payer par chaque
 * redevable est arrondi à l'euro le plus proche, sans arrondi
 * intermédiaire ». L'arrondi par redevable s'opère donc dans
 * {@see FleetFiscalAggregator::companyAnnualTax()},
 * qui somme les `co2DueRaw` + `pollutantsDueRaw` de tous les véhicules
 * d'une entreprise et arrondit **une seule fois**.
 *
 * Cette classe règle est conservée comme **marqueur** dans le pipeline
 * (apparaît dans `appliedRuleCodes` du snapshot, page « Règles de
 * calcul »). Elle ne modifie pas les montants - l'arrondi par couple
 * (utile pour l'affichage par ligne du PDF / drawer planning) est
 * appliqué par le pipeline lui-même dans `buildResult()`.
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
            // CIBS L. 131-1 · article pivot du Livre I qui dispose que
            // les bases d'imposition CIBS sont arrondies « dans les
            // conditions prévues à l'article 1649 undecies du CGI ».
            [
                'type' => 'CIBS',
                'article' => 'L. 131-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044604185/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            // Phase 13 D5.11 (audit sémantique) · article cible du
            // renvoi de L. 131-1 · principe half-up arithmétique
            // (« la fraction d'euro égale à 0,50 est comptée pour 1 »).
            // À noter · 1649 undecies arrondit les bases à **l'euro**
            // au sens général · l'arrondi **au centime** par couple
            // appliqué par Floty (R-2024-003) en dérive par analogie
            // de la règle half-up, formalisé par la pratique BOFiP des
            // taxes CIBS sur les véhicules (BOI-AIS-MOB-10-30-10).
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
