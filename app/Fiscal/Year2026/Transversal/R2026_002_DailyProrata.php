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
use Carbon\CarbonImmutable;

/**
 * R-2026-002 · Prorata journalier · dénominateur **365** en 2026 (année
 * non bissextile, `2026 % 4 = 2`).
 *
 * Mécanique reconduite de R-2025-002 sans modification doctrinale ·
 * texte CIBS L. 421-107 stable depuis 01/01/2022, applicable 2026
 * inchangé. Le dénominateur 365 résulte de l'application du calendrier
 * civil 2026 (non bissextile, identique 2025, vs 366 en 2024).
 *
 * Sémantique ADR-0014 ·
 *   numérateur = totalDays(contractsForPair ∩ year) − Σ exemptDaysCount
 *
 * Les jours exonérés viennent de R-2026-021 (LCD per-contract) et
 * R-2026-008 (indispos réductrices, garde-fou ADR-0016 anti-double-count).
 *
 * **Spécificités 2026** ·
 * - Le `FiscalSegmentedExecutor` segmente automatiquement par VFC
 *   effective (ADR-0021) ET par bornes des règles `applicabilityStart/End`
 *   (notamment les 3 scissions 2026 · R-2026-013/013-bis catégorisation
 *   au 01/09, R-2026-014/014-bis tarif polluants au 01/03,
 *   R-2026-018/018-bis OIG au 01/09). Le résultat du prorata est donc
 *   une somme pondérée par segment-jour, mathématiquement équivalente
 *   à la moyenne pondérée prescrite par CIBS L. 421-108 (cf. R-2026-025).
 *
 * **Base légale** ·
 * - CIBS art. L. 421-107 · version 01/01/2022 → en vigueur (audité
 *   Chrome live 15/05/2026).
 * - URL · https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2026-01-01
 *
 * Arrondi délégué à {@see R2026_003_FinalRounding}.
 */
final readonly class R2026_002_DailyProrata implements TransversalRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-002';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Prorata journalier (365 jours en 2026)';
    }

    public function description(): string
    {
        return 'Mécanique du prorata journalier : tarif annuel plein × (jours affectés / 365) en 2026 (année non bissextile, identique au calendrier 2025 vs 366 en 2024 bissextile).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 2;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-107',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2026-01-01',
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
        $window = $context->daysWindow;
        $totalDates = [];
        foreach ($context->contractsForPair as $contract) {
            foreach ($contract->expandToDaysInYear($context->fiscalYear) as $date) {
                if ($window !== null && ! $window->contains(CarbonImmutable::parse($date))) {
                    continue;
                }
                $totalDates[$date] = true;
            }
        }
        $totalDays = count($totalDates);

        $exemptDays = 0;
        foreach ($context->exemptionVerdicts as $verdict) {
            if ($verdict->exemptDaysCount !== null) {
                $exemptDays += $verdict->exemptDaysCount;
            }
        }

        $daysAssignedToCompany = max(0, $totalDays - $exemptDays);

        $co2Full = $context->co2FullYearTariff ?? 0.0;
        $pollutantsFull = $context->pollutantsFullYearTariff ?? 0.0;
        $denominator = $context->daysInYear;

        $co2Due = $denominator > 0 ? $co2Full * $daysAssignedToCompany / $denominator : 0.0;
        $pollutantsDue = $denominator > 0 ? $pollutantsFull * $daysAssignedToCompany / $denominator : 0.0;

        return $context
            ->withDaysAssignedToCompany($daysAssignedToCompany)
            ->withCumulativeDaysForPair($totalDays)
            ->withDueAmounts($co2Due, $pollutantsDue)
            ->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreImplicite,
            title: 'Prorata journalier',
            pitch: 'Base 365 jours en 2026 (année non bissextile). Taxe due = tarif annuel plein × (jours d\'affectation contractuelle / 365).',
            body: "Toutes les règles de tarification produisent d'abord un tarif annuel plein, qui est ensuite réduit au prorata du nombre de jours de la période d'affectation contractuelle (date de début → date de fin du contrat ; cf. R-2026-022). Les indispos réductrices subies à la demande des pouvoirs publics (R-2026-008) et la qualification LCD du contrat (R-2026-021) sont les seuls mécanismes qui réduisent ce numérateur. Le dénominateur reste à 365 en 2026 (non bissextile, identique à 2025).",
        );
    }
}
