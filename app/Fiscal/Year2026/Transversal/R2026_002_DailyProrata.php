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
 * R-2026-002 - Daily prorata, denominator 365 in 2026 (non-leap year,
 * `2026 % 4 = 2`).
 *
 * Mechanism reproduced from R-2025-002 without doctrinal change.
 * CIBS L. 421-107 stable since 01/01/2022, applicable to 2026
 * unchanged. The denominator 365 results from applying the 2026 civil
 * calendar (non-leap, identical to 2025, vs 366 in 2024).
 *
 * ADR-0014 semantics:
 *   numerator = totalDays(contractsForPair ∩ year) - Σ exemptDaysCount
 *
 * Exempt days come from R-2026-021 (per-contract LCD) and R-2026-008
 * (reductive unavailabilities, ADR-0016 double-counting guard).
 *
 * 2026 specifics: the `FiscalSegmentedExecutor` automatically segments
 * by VFC-effective (ADR-0021) AND by rule `applicabilityStart/End`
 * bounds (notably the 3 2026 splits: R-2026-013/013-bis
 * categorisation at 01/09, R-2026-014/014-bis pollutants tariff at
 * 01/03, R-2026-018/018-bis OIG at 01/09). The prorata result is
 * therefore a per-segment-day weighted sum, mathematically equivalent
 * to the weighted average prescribed by CIBS L. 421-108 (R-2026-025).
 *
 * Legal basis: CIBS art. L. 421-107, version 01/01/2022 in force.
 *
 * Rounding delegated to {@see R2026_003_FinalRounding}.
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
