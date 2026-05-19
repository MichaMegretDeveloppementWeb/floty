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
use Carbon\CarbonImmutable;

/**
 * R-2024-002 · daily prorata (effective use days / dynamic denominator,
 * 366 in leap year 2024).
 *
 * Legal basis: CIBS L. 421-107.
 *
 * Per ADR-0014, the numerator is computed from the pair's contracts
 * (`contractsForPair`) by subtracting the days flagged exempt by the
 * daily exemption rules (R-2024-021 LCD, R-2024-008 reductive
 * unavailabilities):
 *
 *   numerator = totalDays(contractsForPair, year)
 *             − Σ verdicts.exemptDaysCount  (R-2024-021 + R-2024-008)
 *
 * This rule also writes `daysAssignedToCompany` and
 * `cumulativeDaysForPair` onto the context (nullable until now) so the
 * `PipelineResult` can expose them downstream (PDF, breakdown UI).
 *
 * Rounding is not done here; {@see R2024_003_FinalRounding} handles it.
 */
final readonly class R2024_002_DailyProrata implements TransversalRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-002';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Prorata journalier (366 jours en 2024)';
    }

    public function description(): string
    {
        return 'Mécanique du prorata journalier : tarif annuel plein × (jours affectés / 366) en 2024.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2024-06-01',
                'consulted_at' => '2026-05-06',
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
        // 1. Total days in the year across the pair's contracts. The
        // anti-overlap triggers guarantee no overlap between the pair's
        // active contracts but we aggregate through a set to stay
        // strict. If a `daysWindow` is set (VFC-segmented mode), filter
        // present days to keep only those inside the current segment.
        // Contracts are still passed whole to the pipeline so
        // R-2024-021 LCD judges on the full contract duration, not the
        // clipped portion.
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

        // 2. Subtract exempt days (partialDays verdicts emitted by
        // R-2024-021 and R-2024-008).
        $exemptDays = 0;
        foreach ($context->exemptionVerdicts as $verdict) {
            if ($verdict->exemptDaysCount !== null) {
                $exemptDays += $verdict->exemptDaysCount;
            }
        }

        $daysAssignedToCompany = max(0, $totalDays - $exemptDays);

        // 3. Apply the prorata to the annual tariffs (already possibly
        // zeroed by total exemptions: handicap, electric, OIG, …).
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
            pitch: 'Base 366 jours en 2024 (année bissextile). Taxe due = tarif annuel plein × (jours d’affectation contractuelle / 366).',
            body: "Toutes les règles de tarification produisent d'abord un tarif annuel plein, qui est ensuite réduit au prorata du nombre de jours de la période d'affectation contractuelle (date de début → date de fin du contrat ; cf. R-2024-022). Les indispos réductrices subies à la demande des pouvoirs publics (R-2024-008) et la qualification LCD du contrat (R-2024-021) sont les seuls mécanismes qui réduisent ce numérateur.",
        );
    }
}
