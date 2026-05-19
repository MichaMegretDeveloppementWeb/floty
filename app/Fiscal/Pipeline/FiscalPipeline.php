<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\AbatementRule;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Fiscal\ValueObjects\ExemptionScope;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Services\Shared\Fiscal\FiscalYearContext;

/**
 * Orchestrator of the Floty fiscal engine (ADR-0006 § 2 · fixed 8-step
 * pipeline).
 *
 *   1. Context bootstrap (current fiscal characteristics)
 *   2. Classifications (CO₂ method, pollutant category)
 *   3. Cumul (provided by the caller via `contractsForPair` and
 *      `vehicleUnavailabilitiesInYear` on the PipelineContext)
 *   4. Exemptions (verdict collection; out-of-scope short-circuit)
 *   5. Abatements (empty in 2024)
 *   6. Pricing (CO₂ + pollutants)
 *   7. Prorata + rounding (Transversal · R-2024-002 computes the
 *      numerator from taxable contracts and applies the prorata)
 *   8. Structured output (PipelineResult)
 *
 * Per ADR-0014, the pipeline no longer receives aggregated cumuls.
 * It receives raw material (the pair's contracts, the vehicle's
 * unavailabilities) and the sovereign rules R-2024-021 and R-2024-008
 * decide which days are exempted. R-2024-002 (Transversal) computes the
 * final numerator and writes it back to the context.
 */
final class FiscalPipeline
{
    public function __construct(
        private readonly FiscalRuleRegistry $registry,
        private readonly FiscalYearContext $yearContext,
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $fiscalCharacteristics,
    ) {}

    /**
     * Runs the pipeline using the registry's rules for the context year.
     */
    public function execute(PipelineContext $context): PipelineResult
    {
        return $this->executeWithRules(
            $context,
            $this->registry->rulesForYear($context->fiscalYear),
        );
    }

    /**
     * Variant of {@see execute()} that consumes a pre-supplied rule list
     * instead of resolving from the registry. Used by
     * {@see FiscalSegmentedExecutor} to run the pipeline on a temporal
     * sub-segment with only the rules applicable to that sub-segment.
     *
     * @param  list<FiscalRule>  $rules
     */
    public function executeWithRules(PipelineContext $context, array $rules): PipelineResult
    {
        $this->validateInputs($context);

        $context = $this->loadFiscalCharacteristics($context);

        foreach ($this->filterByType($rules, ClassificationRule::class) as $rule) {
            $context = $rule->classify($context);
        }

        // R-2024-004 short-circuit: vehicle out of fiscal scope → all
        // taxes at 0, skip exemptions / abatements / pricing / transversal.
        if ($context->isFiscallyTaxable === false) {
            return $this->buildResult($context);
        }

        foreach ($this->filterByType($rules, ExemptionRule::class) as $rule) {
            $verdict = $rule->evaluate($context);
            if ($verdict->isExempt) {
                $context = $context
                    ->withExemptionVerdict($verdict)
                    ->withAppliedRule($rule->ruleCode());
            }
        }

        foreach ($this->filterByType($rules, AbatementRule::class) as $rule) {
            $context = $rule->abate($context);
        }

        foreach ($this->filterByType($rules, PricingRule::class) as $rule) {
            $context = $rule->price($context);
        }

        // Apply *total* exemption verdicts (handicap, electric, OIG,…)
        // to the tariffs before prorata. Daily verdicts (partialDays,
        // scope null) do not zero the tariffs: they only affect the
        // numerator in R-2024-002.
        $context = $this->applyExemptionsToTariffs($context);

        // Transversals (prorata + rounding). R-2024-002 derives
        // daysAssignedToCompany from contractsForPair and subtracts the
        // partialDays verdicts.
        foreach ($this->filterByType($rules, TransversalRule::class) as $rule) {
            $context = $rule->apply($context);
        }

        return $this->buildResult($context);
    }

    private function validateInputs(PipelineContext $context): void
    {
        if (! $this->yearContext->isSupported($context->fiscalYear)) {
            throw FiscalCalculationException::yearNotSupported($context->fiscalYear);
        }
    }

    private function loadFiscalCharacteristics(PipelineContext $context): PipelineContext
    {
        if ($context->currentFiscalCharacteristics !== null) {
            return $context;
        }

        $vfc = $this->fiscalCharacteristics->findCurrentForVehicle($context->vehicle);
        if ($vfc === null) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $context->withCurrentFiscalCharacteristics($vfc);
    }

    /**
     * @template T of object
     *
     * @param  list<object>  $rules
     * @param  class-string<T>  $type
     * @return list<T>
     */
    private function filterByType(array $rules, string $type): array
    {
        return array_values(array_filter(
            $rules,
            static fn (object $rule): bool => $rule instanceof $type,
        ));
    }

    private function applyExemptionsToTariffs(PipelineContext $context): PipelineContext
    {
        $verdicts = $context->exemptionVerdicts;
        if ($verdicts === []) {
            return $context;
        }

        $hasZeroingTariffs = false;
        $covers = [];
        foreach ($verdicts as $verdict) {
            // Daily verdicts (scope null) do not zero tariffs; they
            // affect the numerator in R-2024-002.
            if ($verdict->scope === null) {
                continue;
            }
            if ($verdict->zeroesFullYearTariffs) {
                $hasZeroingTariffs = true;
            }
            $covers[] = $verdict->scope;
        }

        if ($hasZeroingTariffs) {
            return $context
                ->withCo2FullYearTariff(0.0)
                ->withPollutantsFullYearTariff(0.0);
        }

        $coversBoth = in_array(ExemptionScope::Both, $covers, true);
        $coversCo2 = $coversBoth || in_array(ExemptionScope::Co2Only, $covers, true);
        $coversPollutants = $coversBoth || in_array(ExemptionScope::PollutantsOnly, $covers, true);

        if ($coversCo2) {
            $context = $context->withCo2FullYearTariff(0.0);
        }
        if ($coversPollutants) {
            $context = $context->withPollutantsFullYearTariff(0.0);
        }

        return $context;
    }

    private function buildResult(PipelineContext $context): PipelineResult
    {
        $verdicts = $context->exemptionVerdicts;

        // R-2024-004 special case: vehicle out of fiscal scope. No
        // exemption verdict was emitted (short-circuited before the
        // exemption stage) but a human-readable reason must still be
        // shown. R-2024-004 sets `withFiscallyTaxableReason()` according
        // to the branch taken; the hard-coded fallback is a safety net.
        if ($context->isFiscallyTaxable === false) {
            $appliedExemptions = [new AppliedExemption(
                reason: $context->isFiscallyTaxableReason
                    ?? 'Véhicule hors du champ fiscal des taxes annuelles (CIBS L. 421-2).',
                ruleCode: 'R-2024-004',
            )];
        } else {
            $appliedExemptions = array_values(array_filter(array_map(
                static fn (ExemptionVerdict $v): ?AppliedExemption => $v->reason !== null && $v->ruleCode !== null
                    ? new AppliedExemption($v->reason, $v->ruleCode)
                    : null,
                $verdicts,
            )));
        }

        $handicapExempt = false;
        $electricExempt = false;
        $lcdExempt = false;
        foreach ($verdicts as $verdict) {
            if ($verdict->zeroesFullYearTariffs) {
                $handicapExempt = true;
            }
            if ($verdict->scope === ExemptionScope::Co2Only) {
                $electricExempt = true;
            }
            // LCD marker: set as soon as at least one contract in the
            // pair is qualified LCD (R-2024-021 emitted a partialDays
            // verdict). Per-contract semantics allow a mixed LCD/LLD
            // pair; the flag only signals presence.
            if ($verdict->exemptDaysCount !== null && $verdict->exemptDaysCount > 0) {
                $lcdExempt = true;
            }
        }

        // Raw values: what R-2024-002 (DailyProrata) wrote before
        // rounding. Used by `FleetFiscalAggregator` for per-taxpayer
        // aggregation (R-2024-003 BOFiP semantics · one rounding per
        // company).
        $co2DueRaw = $context->co2Due ?? 0.0;
        $pollutantsDueRaw = $context->pollutantsDue ?? 0.0;

        // Rounded values for per-row display (PDF, planning drawer).
        $co2Due = round($co2DueRaw, 2, PHP_ROUND_HALF_UP);
        $pollutantsDue = round($pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);
        $totalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

        return new PipelineResult(
            daysAssigned: $context->daysAssignedToCompany ?? 0,
            cumulativeDaysForPair: $context->cumulativeDaysForPair ?? 0,
            daysInYear: $context->daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: $handicapExempt,
            co2Method: $context->resolvedCo2Method ?? HomologationMethod::Pa,
            co2FullYearTariff: $context->co2FullYearTariff ?? 0.0,
            co2Due: $co2Due,
            co2DueRaw: $co2DueRaw,
            pollutantCategory: $context->resolvedPollutantCategory ?? PollutantCategory::MostPolluting,
            pollutantsFullYearTariff: $context->pollutantsFullYearTariff ?? 0.0,
            pollutantsDue: $pollutantsDue,
            pollutantsDueRaw: $pollutantsDueRaw,
            totalDue: $totalDue,
            appliedExemptions: $appliedExemptions,
            appliedRuleCodes: $context->appliedRuleCodes,
        );
    }
}
