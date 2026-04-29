<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * R-2024-002 — Prorata journalier (jours d'utilisation effective /
 * dénominateur dynamique selon l'année, 366 en 2024 bissextile).
 *
 * **Sémantique v2.0 (ADR-0014)** :
 * Le numérateur est calculé ici à partir des contrats du couple
 * (`contractsForPair`) en soustrayant les jours signalés exonérés par
 * les règles d'exonération journalière (R-2024-021 LCD,
 * R-2024-008 indispos réductrices).
 *
 *   numérateur = totalDays(contractsForPair, year)
 *              − Σ verdicts.exemptDaysCount  (R-2024-021 + R-2024-008)
 *
 * Cette règle est aussi celle qui pose `daysAssignedToCompany` et
 * `cumulativeDaysForPair` dans le contexte (champs nullable jusqu'ici)
 * pour que `PipelineResult` puisse les exposer aux consommateurs en
 * aval (PDF, breakdown UI).
 *
 * Cette règle ne s'occupe PAS de l'arrondi — c'est le rôle de
 * {@see R2024_003_FinalRounding}.
 */
final readonly class R2024_002_DailyProrata implements TransversalRule
{
    public function ruleCode(): string
    {
        return 'R-2024-002';
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
        // 1. Total jours dans l'année des contrats du couple (les
        // triggers anti-overlap garantissent l'absence de chevauchement
        // entre contrats actifs du couple, mais on agrège via un set
        // pour rester strict).
        $totalDates = [];
        foreach ($context->contractsForPair as $contract) {
            foreach ($contract->expandToDaysInYear($context->fiscalYear) as $date) {
                $totalDates[$date] = true;
            }
        }
        $totalDays = count($totalDates);

        // 2. Soustraction des jours exonérés (verdicts partialDays
        // posés par R-2024-021 et R-2024-008).
        $exemptDays = 0;
        foreach ($context->exemptionVerdicts as $verdict) {
            if ($verdict->exemptDaysCount !== null) {
                $exemptDays += $verdict->exemptDaysCount;
            }
        }

        $daysAssignedToCompany = max(0, $totalDays - $exemptDays);

        // 3. Application du prorata sur les tarifs annuels (déjà
        // éventuellement neutralisés par les exonérations totales —
        // handicap, électrique, OIG, etc.).
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
}
