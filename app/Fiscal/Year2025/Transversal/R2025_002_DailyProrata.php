<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

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
 * R-2025-002 · Prorata journalier (jours d'utilisation effective /
 * dénominateur dynamique selon l'année, **365 en 2025** non bissextile).
 *
 * Reconduction stricte de R-2024-002 avec dénominateur 365 au lieu de
 * 366. La sémantique ADR-0014 (numérateur = jours contractuels du couple
 * moins jours signalés exonérés par R-2025-021 LCD et R-2025-008 indispos
 * réductrices) est identique.
 *
 *   numérateur = totalDays(contractsForPair, year)
 *              − Σ verdicts.exemptDaysCount  (R-2025-021 + R-2025-008)
 *
 * Comme en 2024, cette règle pose `daysAssignedToCompany` et
 * `cumulativeDaysForPair` dans le contexte. L'arrondi est délégué à
 * {@see R2025_003_FinalRounding}.
 *
 * Base légale : CIBS art. L. 421-107, version en vigueur depuis le
 * 01/01/2022, inchangée en 2025. Le dénominateur 365 résulte de
 * l'application de l'article au calendrier civil 2025.
 */
final readonly class R2025_002_DailyProrata implements TransversalRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-002';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Prorata journalier (365 jours en 2025)';
    }

    public function description(): string
    {
        return 'Mécanique du prorata journalier : tarif annuel plein × (jours affectés / 365) en 2025 (année non bissextile).';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2025-01-01',
                'consulted_at' => '2026-05-14',
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
            pitch: 'Base 365 jours en 2025 (année non bissextile). Taxe due = tarif annuel plein × (jours d\'affectation contractuelle / 365).',
            body: "Toutes les règles de tarification produisent d'abord un tarif annuel plein, qui est ensuite réduit au prorata du nombre de jours de la période d'affectation contractuelle (date de début → date de fin du contrat ; cf. R-2025-022). Les indispos réductrices subies à la demande des pouvoirs publics (R-2025-008) et la qualification LCD du contrat (R-2025-021) sont les seuls mécanismes qui réduisent ce numérateur. Le dénominateur passe de 366 (2024 bissextile) à 365 (2025 non bissextile).",
        );
    }
}
