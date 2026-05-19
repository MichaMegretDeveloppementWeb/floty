<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Fiscal\ValueObjects\DaysWindow;
use App\Fiscal\ValueObjects\FiscalSegmentBreakdown;
use App\Fiscal\ValueObjects\RuleEffectiveSegment;
use App\Fiscal\ValueObjects\VfcEffectiveSegment;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;

/**
 * Orchestrates the fiscal engine over the cartesian product of VFC
 * segments × rule segments.
 *
 * Each sub-segment is tariffed with:
 *   - the VFC active over the period,
 *   - the rules applicable over the period,
 *   - a {@see DaysWindow} that clips the day counter in R-2024-002
 *     without altering the contract definitions (R-2024-021 LCD judges
 *     on the contract's full duration, independent of the clipping).
 *
 * Why VFC × rules: a vehicle can hold several VFCs in one year (data
 * correction, CO₂ update) and rules can change mid-year (appearance,
 * disappearance, modification). {@see FiscalPipeline} stays
 * single-segment; this executor orchestrates the temporal union.
 *
 * Segmentation semantics:
 *   - 0 VFC segment in the year → throws `missingFiscalCharacteristics`.
 *   - 0 rule segment in the year (empty registry for that year) →
 *     throws `noViableCalculationWindow`.
 *   - Clipped cartesian: for each `(vfcSeg, ruleSeg)` pair, intersect
 *     `[max(start), min(end)]`. Empty intersections are skipped.
 *   - If every pair has an empty intersection → throws
 *     `noViableCalculationWindow` (degenerate case, distinct from a
 *     missing VFC: the VFC exists but no viable window).
 *   - Perf short-circuit: if exactly one sub-segment results AND covers
 *     the full year, no DaysWindow is set (matches pre-segmentation
 *     mono mode and avoids needless filtering in R-2024-002).
 *
 * 2024 non-regression invariant: all 2024 rules cover the whole year →
 * single rule segment. The cartesian `[N VFC × 1 rule]` produces
 * exactly the same `N` partials as before, so 2024 calculations are
 * strictly identical.
 *
 * Gap between two VFCs: days inside the gap appear in no segment and
 * are not counted, consistent with the fiscal semantics (a vehicle
 * without a VFC at time t cannot be calculated).
 */
final readonly class FiscalSegmentedExecutor
{
    public function __construct(
        private VehicleFiscalCharacteristicsReadRepositoryInterface $vfcRepository,
        private RuleEffectiveSegmenter $ruleSegmenter,
        private FiscalPipeline $pipeline,
    ) {}

    /**
     * Runs the segmented pipeline, fetching VFC segments from the
     * repository.
     */
    public function execute(PipelineContext $context): PipelineResult
    {
        $vfcSegments = $this->fetchVfcSegments($context);

        return $this->buildAndMergeResult($context, $vfcSegments);
    }

    /**
     * Variant of {@see execute()} that consumes a pre-loaded VFC
     * segment list instead of hitting the database. Used to collapse
     * N+1 queries when batching full-year tax for many vehicles (see
     * {@see FleetFiscalAggregator::prewarmFullYearForVehicles()}).
     *
     * Strict precondition: `$vfcSegments` must equal what
     * `findEffectiveSegmentsForYear($context->vehicle, $context->fiscalYear)`
     * would return (typically obtained via
     * {@see VehicleFiscalCharacteristicsReadRepositoryInterface::findEffectiveSegmentsForYearBatch()}).
     *
     * Under that precondition the result is strictly equivalent to
     * `execute($context)` (covered by
     * `FiscalSegmentedExecutorTest::executeWithPreloadedVfcSegments_equivalent_a_execute`).
     *
     * @param  list<VfcEffectiveSegment>  $vfcSegments
     */
    public function executeWithPreloadedVfcSegments(PipelineContext $context, array $vfcSegments): PipelineResult
    {
        if ($vfcSegments === []) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $this->buildAndMergeResult($context, $vfcSegments);
    }

    /**
     * Variant of {@see execute()} that returns the per-sub-segment
     * breakdown (intersection VFC × rules + partial pipeline result)
     * instead of the merged result. Used by consumers that expose a
     * tariff detail per sub-segment in their presentation DTO (e.g.
     * {@see FleetFiscalAggregator::vehicleFullYearTaxBreakdown()}).
     *
     * @return non-empty-list<FiscalSegmentBreakdown>
     */
    public function executeWithSegments(PipelineContext $context): array
    {
        $vfcSegments = $this->fetchVfcSegments($context);

        return $this->buildBreakdowns($context, $vfcSegments);
    }

    /**
     * Variant of {@see executeWithSegments()} that consumes a
     * pre-loaded VFC segment list. Symmetric counterpart of
     * {@see executeWithPreloadedVfcSegments()} for consumers needing
     * the breakdown detail (not the merged result).
     *
     * Strict precondition identical to
     * {@see executeWithPreloadedVfcSegments()}.
     *
     * @param  list<VfcEffectiveSegment>  $vfcSegments
     * @return non-empty-list<FiscalSegmentBreakdown>
     */
    public function executeWithSegmentsAndPreloadedVfc(PipelineContext $context, array $vfcSegments): array
    {
        if ($vfcSegments === []) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $this->buildBreakdowns($context, $vfcSegments);
    }

    /**
     * @return non-empty-list<VfcEffectiveSegment>
     */
    private function fetchVfcSegments(PipelineContext $context): array
    {
        $vfcSegments = $this->vfcRepository->findEffectiveSegmentsForYear(
            $context->vehicle,
            $context->fiscalYear,
        );

        if ($vfcSegments === []) {
            throw FiscalCalculationException::missingFiscalCharacteristics($context->vehicle->id);
        }

        return $vfcSegments;
    }

    /**
     * Runs the pipeline on each cartesian sub-segment VFC × rules and
     * merges into a single `PipelineResult`. Shared core between
     * {@see execute()} and {@see executeWithPreloadedVfcSegments()} to
     * guarantee strict equivalence between the two paths.
     *
     * @param  non-empty-list<VfcEffectiveSegment>  $vfcSegments
     */
    private function buildAndMergeResult(PipelineContext $context, array $vfcSegments): PipelineResult
    {
        $breakdowns = $this->buildBreakdowns($context, $vfcSegments);

        if (count($breakdowns) === 1) {
            return $breakdowns[0]->result;
        }

        return $this->mergeResults(array_map(
            static fn (FiscalSegmentBreakdown $b): PipelineResult => $b->result,
            $breakdowns,
        ));
    }

    /**
     * Builds the cartesian sub-segments VFC × rules and runs the
     * pipeline on each.
     *
     * @param  non-empty-list<VfcEffectiveSegment>  $vfcSegments
     * @return non-empty-list<FiscalSegmentBreakdown>
     */
    private function buildBreakdowns(PipelineContext $context, array $vfcSegments): array
    {
        $ruleSegments = $this->ruleSegmenter->segmentsForYear($context->fiscalYear);

        if ($ruleSegments === []) {
            // Degenerate case: year is declared in the registry but no
            // rule is effective over the year (empty registry). Distinct
            // from a missing VFC: VFC exists but no calculable rule.
            throw FiscalCalculationException::noViableCalculationWindow(
                $context->vehicle->id,
                $context->fiscalYear,
            );
        }

        // Clipped cartesian product: keep only pairs with a non-empty
        // intersection.
        /** @var list<array{vfc: VfcEffectiveSegment, rule: RuleEffectiveSegment, start: CarbonImmutable, end: CarbonImmutable}> $pairs */
        $pairs = [];
        foreach ($vfcSegments as $vfcSeg) {
            foreach ($ruleSegments as $ruleSeg) {
                $start = $vfcSeg->start->greaterThan($ruleSeg->start) ? $vfcSeg->start : $ruleSeg->start;
                $end = $vfcSeg->end->lessThan($ruleSeg->end) ? $vfcSeg->end : $ruleSeg->end;

                if ($start->greaterThan($end)) {
                    continue;
                }

                $pairs[] = [
                    'vfc' => $vfcSeg,
                    'rule' => $ruleSeg,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        if ($pairs === []) {
            // Cartesian produced no non-empty intersection (e.g. 2025
            // rules effective 01/07–31/12 + VFC ending 15/06/2025).
            throw FiscalCalculationException::noViableCalculationWindow(
                $context->vehicle->id,
                $context->fiscalYear,
            );
        }

        $singleCoversYear = count($pairs) === 1
            && $this->coversFullYear($pairs[0]['start'], $pairs[0]['end'], $context->fiscalYear);

        $breakdowns = [];
        foreach ($pairs as $p) {
            $segmentContext = $context->withCurrentFiscalCharacteristics($p['vfc']->vfc);
            // In single-segment full-year mode, skip the window (perf:
            // avoid filtering inside R-2024-002 when no day is dropped).
            if (! $singleCoversYear) {
                $segmentContext = $segmentContext->withDaysWindow(
                    new DaysWindow($p['start'], $p['end']),
                );
            }

            $breakdowns[] = new FiscalSegmentBreakdown(
                start: $p['start'],
                end: $p['end'],
                vfcSegment: $p['vfc'],
                ruleSegment: $p['rule'],
                result: $this->pipeline->executeWithRules($segmentContext, $p['rule']->rules),
            );
        }

        return $breakdowns;
    }

    private function coversFullYear(CarbonImmutable $start, CarbonImmutable $end, int $fiscalYear): bool
    {
        return $start->month === 1 && $start->day === 1
            && $end->month === 12 && $end->day === 31
            && $start->year === $fiscalYear
            && $end->year === $fiscalYear;
    }

    /**
     * Merges partial results (one per cartesian sub-segment).
     *
     * Rules:
     *   - `daysAssigned`, `cumulativeDaysForPair`: sum.
     *   - `co2DueRaw`, `pollutantsDueRaw`: sum (raw, pre-rounding).
     *   - `co2Due`, `pollutantsDue`, `totalDue`: recomputed via
     *     `round(sum_raw, 2, HALF_UP)`; `totalDue = round(co2 +
     *     pollutants, 2, HALF_UP)`. Matches
     *     {@see FiscalPipeline::buildResult()}.
     *   - `co2Method`, `pollutantCategory`, tariffs: taken from the
     *     first sub-segment (UI consumers expose the segmented list in
     *     their breakdown DTO).
     *   - Exemption flags (`lcdExempt`, `electricExempt`,
     *     `handicapExempt`): logical OR.
     *   - `appliedExemptions`: deduplicated union by `ruleCode`.
     *   - `appliedRuleCodes`: deduplicated union.
     *
     * @param  non-empty-list<PipelineResult>  $partials
     */
    private function mergeResults(array $partials): PipelineResult
    {
        $first = $partials[0];

        $daysAssigned = 0;
        $cumulativeDays = 0;
        $co2Raw = 0.0;
        $pollutantsRaw = 0.0;
        $lcdExempt = false;
        $electricExempt = false;
        $handicapExempt = false;
        /** @var array<string, AppliedExemption> $exemptionsByCode */
        $exemptionsByCode = [];
        /** @var array<string, true> $ruleCodesSet */
        $ruleCodesSet = [];

        foreach ($partials as $partial) {
            $daysAssigned += $partial->daysAssigned;
            $cumulativeDays += $partial->cumulativeDaysForPair;
            $co2Raw += $partial->co2DueRaw;
            $pollutantsRaw += $partial->pollutantsDueRaw;
            $lcdExempt = $lcdExempt || $partial->lcdExempt;
            $electricExempt = $electricExempt || $partial->electricExempt;
            $handicapExempt = $handicapExempt || $partial->handicapExempt;
            foreach ($partial->appliedExemptions as $exemption) {
                $exemptionsByCode[$exemption->ruleCode] ??= $exemption;
            }
            foreach ($partial->appliedRuleCodes as $ruleCode) {
                $ruleCodesSet[$ruleCode] = true;
            }
        }

        $co2Due = round($co2Raw, 2, PHP_ROUND_HALF_UP);
        $pollutantsDue = round($pollutantsRaw, 2, PHP_ROUND_HALF_UP);
        $totalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

        return new PipelineResult(
            daysAssigned: $daysAssigned,
            cumulativeDaysForPair: $cumulativeDays,
            daysInYear: $first->daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: $handicapExempt,
            co2Method: $first->co2Method,
            co2FullYearTariff: $first->co2FullYearTariff,
            co2Due: $co2Due,
            co2DueRaw: $co2Raw,
            pollutantCategory: $first->pollutantCategory,
            pollutantsFullYearTariff: $first->pollutantsFullYearTariff,
            pollutantsDue: $pollutantsDue,
            pollutantsDueRaw: $pollutantsRaw,
            totalDue: $totalDue,
            appliedExemptions: array_values($exemptionsByCode),
            appliedRuleCodes: array_keys($ruleCodesSet),
        );
    }
}
