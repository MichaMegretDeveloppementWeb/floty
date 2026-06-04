<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Data\User\Contract\ContractTaxBreakdownData;
use App\Data\User\Contract\ContractTaxYearBreakdownData;
use App\Data\User\Contract\ContractTaxYearSegmentBreakdownData;
use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Data\User\Vehicle\VehicleFiscalCharacteristicsData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\Data\User\Vehicle\VehicleFullYearTaxSegmentData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Enums\Fiscal\SegmentBoundaryCause;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\PipelineResult;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Fiscal\ValueObjects\FiscalSegmentBreakdown;
use App\Fiscal\ValueObjects\VfcEffectiveSegment;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\FiscalRule\FiscalRuleQueryService;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Collection;

/**
 * Fleet-wide annual fiscal aggregator · the fiscal core of Floty
 * (CIBS CO₂ tax + pollutant tax).
 *
 * Centralises the tax summations (per vehicle, per company, per fleet)
 * that used to be duplicated across four controllers (Vehicle,
 * Company, Dashboard, Planning). Source of truth for fiscal
 * computations (ADR-0022 · PHP classes as the source of truth, ADR-0006
 * · fiscal engine V1).
 *
 * R-2024-003 · the half-up centime rounding (`round(.., 2, ..)`) is
 * applied once per taxpayer (using company), never per intermediate
 * couple. The aggregator sums `*DueRaw` from `PipelineResult` and
 * rounds at the end (ADR-0006 § 2). The 2024-passing handles `ContractsByPair`
 * (ADR-0014); unavailabilities per vehicle are passed separately
 * (`vehicleId → list<VehicleEvent>`) to feed R-2024-008.
 *
 * --------------------------------------------------------------------------
 * Stability notice · NOT refactored under the zero-debt doctrine.
 * --------------------------------------------------------------------------
 *
 * This service is ~620 lines, ~38 % of which is explanatory comments
 * (BOFiP references, CIBS legal basis, worked examples). It is
 * intentionally above the SRP target of 500 lines because ·
 *
 *   - It is the CIBS fiscal core · a poor refactor risks producing
 *     incorrect tax in production (regulatory compliance LFi 2024 art. 14).
 *   - Comments are explanatory and serve as audit material.
 *   - The refactor cost (equivalence tests on ≥10 fixtures + exhaustive
 *     Chrome live) outweighs the maintainability gain.
 *   - ADR-0022 designates this service as the fiscal source of truth ·
 *     prudent preservation beats cosmetic SRP.
 *
 * Any change here must ·
 *   1. Have a prior equivalence test on ≥ 3 representative fixtures.
 *   2. Be validated through Chrome live on the declaration generator.
 *   3. Cross-check against ≥ 2 existing CIBS computations.
 *
 * Safety net · `FiscalCalculatorTest`, `FiscalAdditivityTest`,
 * `FiscalInvariantsTest`, `MultiYearContractTest`, `MultiVfcEdgeCasesTest`.
 * Every change must stay green there.
 */
final class FleetFiscalAggregator
{
    /**
     * Per-instance memoization of rule DTOs by `"{year}|{sortedCodes}"`.
     * Avoids rebuilding when the aggregator is reused across vehicles
     * and contracts within one request. DTOs come from the registry
     * (PHP classes), no DB read.
     *
     * @var array<string, list<FiscalRuleListItemData>>
     */
    private array $rulesCache = [];

    /**
     * Per-instance memoization of full-year `PipelineResult` indexed by
     * `"{vehicleId}|{year}"`. The result depends only on
     * `(vehicle, year)` (synthetic full-year contract, no
     * unavailabilities), so it is shareable between
     * `vehicleFullYearTax` and `vehicleFullYearTaxBreakdown`.
     *
     * @var array<string, PipelineResult>
     */
    private array $fullYearResultCache = [];

    /**
     * Per-instance memoization of `VehicleFullYearTaxBreakdownData` by
     * `"{vehicleId}|{year}"`. The DTO is deterministic on
     * `(vehicle, year)` and safe to memoize for the lifetime of the
     * Laravel service (per-request resolution).
     *
     * @var array<string, VehicleFullYearTaxBreakdownData>
     */
    private array $fullYearBreakdownCache = [];

    /**
     * Per-instance memoization of "per-couple" aggregates. Scalar keys
     * only; the other arguments (`Collection<Vehicle>`,
     * `ContractsByPair`, unavailabilities) always come from the same
     * `DashboardScopeContext` / `CompanyDetailScope` for a given
     * `(companyId, year)` or `(vehicleId, year)` within a request.
     *
     * Safety · `ContractsByPair` is `final readonly` (immutable), no
     * mutation between calls. `Collection<Vehicle>` is conceptually
     * passed by value (not mutated by the methods).
     *
     * @var array<string, float>
     */
    private array $vehicleAnnualTaxCache = [];

    /** @var array<string, float> */
    private array $companyAnnualTaxCache = [];

    /** @var array<string, list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>> */
    private array $vehicleAnnualTaxBreakdownByCompanyCache = [];

    /** @var array<string, list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float, appliedExemptions: list<AppliedExemption>}>> */
    private array $companyAnnualTaxBreakdownByVehicleCache = [];

    /** @var array<string, float> */
    private array $fleetAnnualTaxCache = [];

    /**
     * Per-instance cache of effective VFC segments keyed by
     * `"{vehicleId}|{year}"`. Filled by
     * {@see prewarmVfcSegmentsForVehicles()} via a single
     * `findEffectiveSegmentsForYearBatch` SQL query and consumed by
     * {@see vehicleFullYearTaxBreakdown()} and
     * {@see vehicleAnnualTax()} to avoid the N+1 VFC query.
     *
     * Cache semantics · `[]` (no segments) is a valid value (vehicle
     * with no VFC for the year · throws
     * `missingFiscalCharacteristics` at execution time). `isset()`
     * indicates a cache hit; `[]` triggers the same throw as the
     * no-cache path, so we do not distinguish strictly.
     *
     * @var array<string, list<VfcEffectiveSegment>>
     */
    private array $vfcSegmentsCache = [];

    public function __construct(
        private readonly FiscalSegmentedExecutor $pipeline,
        private readonly FiscalYearContext $yearContext,
        private readonly FiscalRuleQueryService $rulesQuery,
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $vfcRepository,
    ) {}

    /**
     * Annual fiscal total for one vehicle, summed across every
     * company it has been attributed to.
     *
     * The vehicle must come with its active `fiscalCharacteristics`
     * preloaded (otherwise the pipeline issues a fresh repository
     * query per call).
     *
     * @param  list<VehicleEvent>  $vehicleEvents  Year unavailabilities of the vehicle
     */
    public function vehicleAnnualTax(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleEvents,
        int $year,
    ): float {
        $key = $vehicle->id.'|'.$year;

        return $this->vehicleAnnualTaxCache[$key] ??= $this->computeVehicleAnnualTax(
            $vehicle,
            $contracts,
            $vehicleEvents,
            $year,
        );
    }

    /**
     * @param  list<VehicleEvent>  $vehicleEvents
     */
    private function computeVehicleAnnualTax(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleEvents,
        int $year,
    ): float {
        // Optimised branch when the VFC cache is prewarmed (see
        // {@see prewarmVfcSegmentsForVehicles()}). Strict equivalence
        // is guaranteed; an empty `$cachedSegments` (vehicle without
        // VFC) still triggers the `missingFiscalCharacteristics`
        // throw in `executeWithPreloadedVfcSegments`, mirroring the
        // no-cache path.
        $cachedSegments = $this->vfcSegmentsCache[$vehicle->id.'|'.$year] ?? null;

        $totalRaw = 0.0;
        foreach ($contracts->pairsForVehicle($vehicle->id) as $pairContracts) {
            $context = $this->buildContext($vehicle, $pairContracts, $vehicleEvents, $year);
            $result = $cachedSegments !== null
                ? $this->pipeline->executeWithPreloadedVfcSegments($context, $cachedSegments)
                : $this->pipeline->execute($context);
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        // Half-up rounding to the euro on a vehicle's annual tax
        // (visual consistency with the other taxpayer aggregates,
        // CIBS L. 131-1).
        return round($totalRaw, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Annual fiscal total for one company, summed across every
     * vehicle it has used. Implements R-2024-003 · single rounding
     * per taxpayer.
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexed by id
     * @param  array<int, list<VehicleEvent>>  $vehicleEventsByVehicleId
     */
    public function companyAnnualTax(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $vehicleEventsByVehicleId,
        int $year,
    ): float {
        $key = $companyId.'|'.$year;

        return $this->companyAnnualTaxCache[$key] ??= $this->computeCompanyAnnualTax(
            $companyId,
            $vehiclesById,
            $contracts,
            $vehicleEventsByVehicleId,
            $year,
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<VehicleEvent>>  $vehicleEventsByVehicleId
     */
    private function computeCompanyAnnualTax(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $vehicleEventsByVehicleId,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($contracts->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $result = $this->pipeline->execute(
                $this->buildContext(
                    $vehicle,
                    $pairContracts,
                    $vehicleEventsByVehicleId[$vehicleId] ?? [],
                    $year,
                ),
            );
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        // Half-up rounding to the euro on the taxpayer total
        // (CIBS L. 131-1 + BOI-AIS-MOB-10-30-10). Aligned with
        // `DeclarationFiscalEngine::compute`, which serves the
        // official snapshots.
        return round($totalRaw, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Theoretical full-year vehicle tax · what the vehicle would
     * cost if attributed 100 % of the year to a single company
     * (no LCD, no unavailability, prorata = 1.0).
     *
     * Built from a synthetic non-persisted contract (Jan 1 → Dec 31)
     * so it goes through the normal pipeline. By construction the
     * contract is not LCD (length > 30 days, not a whole civil
     * month) and there is no unavailability, so R-2024-021 and
     * R-2024-008 take nothing from the numerator; R-2024-002
     * computes prorata = daysInYear / daysInYear = 1.0.
     */
    public function vehicleFullYearTax(Vehicle $vehicle, int $year): float
    {
        $result = $this->fullYearPipelineResult($vehicle, $year);

        return round($result->co2DueRaw + $result->pollutantsDueRaw, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Prewarms `$fullYearResultCache` for a batch of vehicles in a
     * single SQL query rather than N. No return · the effect is on
     * the cache, consumed by subsequent calls to
     * {@see vehicleFullYearTax()} / {@see vehicleFullYearTaxBreakdown()}
     * on the same `(vehicleId, year)` pairs.
     *
     * Typical use · Contracts Index
     * (`ContractQueryService::costsForContractIds`) showing 25
     * contracts across 21 distinct vehicles · without the prewarm,
     * 21 individual VFC queries; with the prewarm, a single SQL
     * feeds them all.
     *
     * Strict equivalence guaranteed against the per-call path
     * (covered by
     * `FleetFiscalAggregatorTest::prewarm_equivalent_aux_appels_individuels`).
     * Idempotent · no-op for already-cached vehicles.
     *
     * @param  iterable<Vehicle>  $vehicles
     */
    public function prewarmFullYearForVehicles(iterable $vehicles, int $year): void
    {
        $missing = [];
        $missingById = [];
        foreach ($vehicles as $vehicle) {
            $key = $vehicle->id.'|'.$year;
            if (! isset($this->fullYearResultCache[$key]) && ! isset($missingById[$vehicle->id])) {
                $missing[] = $vehicle;
                $missingById[$vehicle->id] = true;
            }
        }

        if ($missing === []) {
            return;
        }

        $segmentsByVehicleId = $this->vfcRepository->findEffectiveSegmentsForYearBatch(
            array_keys($missingById),
            $year,
        );

        foreach ($missing as $vehicle) {
            $segments = $segmentsByVehicleId[$vehicle->id] ?? [];
            if ($segments === []) {
                // Consistent with `execute()` · without VFC, the next
                // computation throws. The prewarm does not mask that
                // error by caching `null` · the later call will
                // raise the exception when the result is needed.
                continue;
            }

            $context = $this->buildContext(
                $vehicle,
                [$this->fullYearSyntheticContract($year)],
                [],
                $year,
            );
            $this->fullYearResultCache[$vehicle->id.'|'.$year] = $this->pipeline->executeWithPreloadedVfcSegments(
                $context,
                $segments,
            );
        }
    }

    /**
     * Prewarms `$vfcSegmentsCache` for a batch of vehicles. A single
     * `findEffectiveSegmentsForYearBatch` SQL feeds the cache for
     * every missing vehicle; subsequent calls to
     * {@see vehicleFullYearTaxBreakdown()} and
     * {@see vehicleAnnualTax()} read the cache instead of fetching
     * VFCs one at a time (N+1 removed).
     *
     * Typical use · the Planning service iterating ~64 vehicles
     * across two consumer methods · without the prewarm, 64-128
     * individual VFC queries inside the loops; with it, a single
     * SQL covers them all. Strict equivalence covered by
     * `FleetFiscalAggregatorTest::prewarm_vfc_segments_equivalent_*`.
     * Idempotent.
     *
     * @param  iterable<Vehicle>  $vehicles
     */
    public function prewarmVfcSegmentsForVehicles(iterable $vehicles, int $year): void
    {
        $missingIds = [];
        foreach ($vehicles as $vehicle) {
            if (! isset($this->vfcSegmentsCache[$vehicle->id.'|'.$year])) {
                $missingIds[$vehicle->id] = true;
            }
        }

        if ($missingIds === []) {
            return;
        }

        $batch = $this->vfcRepository->findEffectiveSegmentsForYearBatch(
            array_keys($missingIds),
            $year,
        );

        foreach ($missingIds as $vehicleId => $_) {
            // Cache even vehicles without VFC (value `[]`) to avoid a
            // wasted second query if the same vehicle is requested
            // again. The `missingFiscalCharacteristics` throw still
            // fires at pipeline execution time, just like the no-cache
            // path.
            $this->vfcSegmentsCache[$vehicleId.'|'.$year] = $batch[$vehicleId] ?? [];
        }
    }

    /**
     * Full breakdown of a vehicle's annual tax · used by the
     * sidebar of the Show page to explain how the total was reached
     * (CO₂ method, pollutant category, applied exemptions, rule
     * codes).
     *
     * Memoized per-request through `$fullYearBreakdownCache` so the
     * N vehicles × M years loops (Fleet Index, vehicle selector,
     * planning heatmap) only pay the pipeline once per pair.
     */
    public function vehicleFullYearTaxBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $key = $vehicle->id.'|'.$year;

        return $this->fullYearBreakdownCache[$key]
            ??= $this->computeVehicleFullYearTaxBreakdown($vehicle, $year);
    }

    private function computeVehicleFullYearTaxBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        // The pipeline runs against a synthetic full-year contract
        // (Jan 1 → Dec 31) to compute the full-year tax. The
        // orchestrator auto-segments by VFC · one breakdown in
        // single-VFC vehicles, N in multi-VFC.
        $context = $this->buildContext(
            $vehicle,
            [$this->fullYearSyntheticContract($year)],
            [],
            $year,
        );
        // Optimised branch when the VFC cache is prewarmed.
        // Equivalence with the no-cache path is guaranteed; an empty
        // `$cachedSegments` triggers the
        // `missingFiscalCharacteristics` throw inside the executor,
        // matching the no-cache behaviour.
        $cachedSegments = $this->vfcSegmentsCache[$vehicle->id.'|'.$year] ?? null;
        $breakdowns = $cachedSegments !== null
            ? $this->pipeline->executeWithSegmentsAndPreloadedVfc($context, $cachedSegments)
            : $this->pipeline->executeWithSegments($context);

        $taxSegments = [];
        $totalRaw = 0.0;
        /** @var array<string, AppliedExemptionData> $exemptionsByCode */
        $exemptionsByCode = [];
        /** @var array<string, true> $ruleCodesSet */
        $ruleCodesSet = [];
        $previous = null;

        foreach ($breakdowns as $breakdown) {
            $vfc = $breakdown->vfcSegment->vfc;
            $result = $breakdown->result;

            $co2Tariff = round($result->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
            $pollutantsTariff = round($result->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
            $co2Due = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $pollutantsDue = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

            $taxSegments[] = new VehicleFullYearTaxSegmentData(
                effectiveFromInYear: $breakdown->start->toDateString(),
                effectiveToInYear: $breakdown->end->toDateString(),
                daysInSegment: $breakdown->days(),
                boundaryCause: $this->resolveBoundaryCause($breakdown, $previous),
                vfc: VehicleFiscalCharacteristicsData::fromModel($vfc),
                co2Method: $result->co2Method,
                co2FullYearTariff: $co2Tariff,
                co2Explanation: $this->buildCo2Explanation($vfc, $result->co2Method, $co2Tariff, $year),
                co2Due: $co2Due,
                pollutantCategory: $result->pollutantCategory,
                pollutantsFullYearTariff: $pollutantsTariff,
                pollutantsExplanation: $this->buildPollutantsExplanation($vfc, $result->pollutantCategory, $pollutantsTariff),
                pollutantsDue: $pollutantsDue,
                appliedExemptions: array_map(
                    static fn ($e) => AppliedExemptionData::fromValueObject($e),
                    $result->appliedExemptions,
                ),
                appliedRuleCodes: $result->appliedRuleCodes,
            );

            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
            foreach ($result->appliedExemptions as $exemption) {
                $exemptionsByCode[$exemption->ruleCode] ??= AppliedExemptionData::fromValueObject($exemption);
            }
            foreach ($result->appliedRuleCodes as $code) {
                $ruleCodesSet[$code] = true;
            }

            $previous = $breakdown;
        }

        $appliedRuleCodes = array_keys($ruleCodesSet);
        $appliedRules = $this->loadRulesByCodes($year, $appliedRuleCodes);

        return new VehicleFullYearTaxBreakdownData(
            daysInYear: $this->yearContext->daysInYear($year),
            // Taxpayer total rounded half-up to the euro (CIBS L. 131-1).
            // The per-segment `co2Due/pollutantsDue` stay at centime
            // precision · they are detail lines, not declaratory
            // totals.
            total: round($totalRaw, 0, PHP_ROUND_HALF_UP),
            appliedExemptions: array_values($exemptionsByCode),
            appliedRuleCodes: $appliedRuleCodes,
            appliedRules: $appliedRules,
            taxSegments: $this->mergeEquivalentSegments($taxSegments),
        );
    }

    /**
     * Merges consecutive segments that produce identical observable
     * outputs (same VFC, method, category, full-year tariffs and
     * exemption set). Such cuts come from purely editorial rule splits
     * (typically `R-YYYY-NNN` vs `R-YYYY-NNN-bis` reframings whose tab
     * is `RuleTab::Cadre`) and would mislead the user with a "Nouveau
     * barème fiscal" label when no tariff actually changed. Sum is
     * preserved by construction: `tariff × (d1+d2) / Y == tariff × d1 / Y
     * + tariff × d2 / Y` modulo the centime rounding, which we re-apply.
     *
     * @param  list<VehicleFullYearTaxSegmentData>  $segments
     * @return list<VehicleFullYearTaxSegmentData>
     */
    private function mergeEquivalentSegments(array $segments): array
    {
        $merged = [];
        foreach ($segments as $segment) {
            $lastIndex = count($merged) - 1;
            $last = $lastIndex >= 0 ? $merged[$lastIndex] : null;

            if ($last !== null && $this->areSegmentsEquivalent($last, $segment)) {
                $merged[$lastIndex] = new VehicleFullYearTaxSegmentData(
                    effectiveFromInYear: $last->effectiveFromInYear,
                    effectiveToInYear: $segment->effectiveToInYear,
                    daysInSegment: $last->daysInSegment + $segment->daysInSegment,
                    boundaryCause: $last->boundaryCause,
                    vfc: $last->vfc,
                    co2Method: $last->co2Method,
                    co2FullYearTariff: $last->co2FullYearTariff,
                    co2Explanation: $last->co2Explanation,
                    co2Due: round($last->co2Due + $segment->co2Due, 2, PHP_ROUND_HALF_UP),
                    pollutantCategory: $last->pollutantCategory,
                    pollutantsFullYearTariff: $last->pollutantsFullYearTariff,
                    pollutantsExplanation: $last->pollutantsExplanation,
                    pollutantsDue: round($last->pollutantsDue + $segment->pollutantsDue, 2, PHP_ROUND_HALF_UP),
                    appliedExemptions: $last->appliedExemptions,
                    appliedRuleCodes: array_values(array_unique(array_merge(
                        $last->appliedRuleCodes,
                        $segment->appliedRuleCodes,
                    ))),
                );
            } else {
                $merged[] = $segment;
            }
        }

        return $merged;
    }

    /**
     * Two segments are equivalent when their observable tariffs and
     * exemptions match. Observable equality is the safest predicate:
     * any rule whose change does not impact the result is, by
     * definition, editorial.
     */
    private function areSegmentsEquivalent(
        VehicleFullYearTaxSegmentData $a,
        VehicleFullYearTaxSegmentData $b,
    ): bool {
        if ($a->vfc->id !== $b->vfc->id) {
            return false;
        }
        if ($a->co2Method !== $b->co2Method) {
            return false;
        }
        if ($a->pollutantCategory !== $b->pollutantCategory) {
            return false;
        }
        if (abs($a->co2FullYearTariff - $b->co2FullYearTariff) > 0.005) {
            return false;
        }
        if (abs($a->pollutantsFullYearTariff - $b->pollutantsFullYearTariff) > 0.005) {
            return false;
        }

        $codesA = array_map(static fn (AppliedExemptionData $e): string => $e->ruleCode, $a->appliedExemptions);
        $codesB = array_map(static fn (AppliedExemptionData $e): string => $e->ruleCode, $b->appliedExemptions);
        sort($codesA);
        sort($codesB);

        return $codesA === $codesB;
    }

    /**
     * Classify the boundary of a segment relative to its predecessor.
     *
     * The first segment of the year is always `Initial`. For subsequent
     * segments, the cause is derived from which dimension cut at the
     * segment start: the VFC switched, the rule window switched, or
     * both happened at the same boundary.
     */
    private function resolveBoundaryCause(
        FiscalSegmentBreakdown $current,
        ?FiscalSegmentBreakdown $previous,
    ): SegmentBoundaryCause {
        if ($previous === null) {
            return SegmentBoundaryCause::Initial;
        }

        $vfcChanged = $current->vfcSegment->vfc->id !== $previous->vfcSegment->vfc->id;
        $ruleChanged = ! $current->ruleSegment->start->equalTo($previous->ruleSegment->start)
            || ! $current->ruleSegment->end->equalTo($previous->ruleSegment->end);

        return match (true) {
            $vfcChanged && $ruleChanged => SegmentBoundaryCause::Both,
            $vfcChanged => SegmentBoundaryCause::Vfc,
            $ruleChanged => SegmentBoundaryCause::Rule,
            default => SegmentBoundaryCause::Initial,
        };
    }

    /**
     * Full fiscal detail of a single contract, displayed in the
     * « Taxes générées » section of the contract Show page.
     *
     * The pipeline runs per year. A contract crossing two calendar
     * years (e.g. Nov 1, 2024 → Jan 31, 2025) runs the pipeline
     * twice and aggregates.
     *
     * Per-window granularity · uses `executeWithSegments()` to expose
     * each VFC × rules sub-period in the DTO. Required when a scale
     * changes mid-year (e.g. 2026 pollutants +30 % on 01/03, LF 2026
     * art. 58 V IV) or when the VFC evolves. Year-level aggregates
     * remain in the flat fields for the summary header.
     *
     * Caller must eager-load `$contract->vehicle->fiscalCharacteristics`
     * (see `ContractReadRepository::findByIdWithRelations`).
     *
     * @param  list<VehicleEvent>  $vehicleEvents
     */
    public function contractTaxBreakdown(
        Contract $contract,
        array $vehicleEvents,
    ): ContractTaxBreakdownData {
        $vehicle = $contract->vehicle;
        $startYear = $contract->start_date->year;
        $endYear = $contract->end_date->year;

        $years = [];
        $totalRaw = 0.0;

        for ($year = $startYear; $year <= $endYear; $year++) {
            $context = $this->buildContext($vehicle, [$contract], $vehicleEvents, $year);
            // Optimised branch when the VFC cache is prewarmed (same
            // pattern as in {@see vehicleFullYearTaxBreakdown()}).
            $cachedSegments = $this->vfcSegmentsCache[$vehicle->id.'|'.$year] ?? null;
            $breakdowns = $cachedSegments !== null
                ? $this->pipeline->executeWithSegmentsAndPreloadedVfc($context, $cachedSegments)
                : $this->pipeline->executeWithSegments($context);

            $daysInContractInYear = $contract->countDaysInYear($year);

            $segmentsDto = [];
            $co2RawYear = 0.0;
            $pollutantsRawYear = 0.0;
            $daysAssignedYear = 0;
            /** @var array<string, AppliedExemption> $exemptionsByCode */
            $exemptionsByCode = [];
            /** @var array<string, true> $ruleCodesSet */
            $ruleCodesSet = [];

            foreach ($breakdowns as $b) {
                $r = $b->result;

                // Case 1 · segment outside the contract window (e.g.
                // a VFC split that produces a segment outside the
                // contract period). Skip entirely · its rules were
                // not "applied" to this contract. Signature · zero
                // assigned days AND no real exemption.
                if ($r->daysAssigned === 0 && $r->appliedExemptions === []) {
                    continue;
                }

                // Always collect rules and exemptions when the
                // segment has real impact on the contract (assigned
                // days OR exemption that drove days to 0). Lets pure
                // LCD, out-of-scope vehicles (R-2024-004) or electric
                // vehicles (R-2024-013) surface their reasons in the
                // « Exonérations appliquées » section, even when
                // daysAssigned is 0.
                //
                // Dedup by `(ruleCode, reason)` · rules that apply on
                // multiple distinct segments with different wording
                // (e.g. R-YYYY-008 reductive unavailability split by
                // a scale scission · "28 jours" in Feb + "31 jours"
                // in Mar) must keep both lines so the displayed sum
                // matches the total actually subtracted from the
                // numerator. Deduping on ruleCode alone would mask
                // the second contribution.
                foreach ($r->appliedExemptions as $exemption) {
                    $key = $exemption->ruleCode.'|'.$exemption->reason;
                    $exemptionsByCode[$key] ??= $exemption;
                }
                foreach ($r->appliedRuleCodes as $code) {
                    $ruleCodesSet[$code] = true;
                }

                // Case 2 · fully exempted segment (daysAssigned = 0
                // but appliedExemptions non-empty). No segmentsDto
                // line (would be a misleading « 00 j ») and no
                // numeric aggregation (already 0).
                if ($r->daysAssigned === 0) {
                    continue;
                }

                // Case 3 · taxable segment · segmentsDto + numeric
                // aggregates.
                $segCo2Tariff = round($r->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
                $segPollutantsTariff = round($r->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
                $segCo2Due = round($r->co2DueRaw, 2, PHP_ROUND_HALF_UP);
                $segPollutantsDue = round($r->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

                $segmentsDto[] = new ContractTaxYearSegmentBreakdownData(
                    effectiveFromInYear: $b->start->toDateString(),
                    effectiveToInYear: $b->end->toDateString(),
                    daysAssignedToContract: $r->daysAssigned,
                    daysInYear: $r->daysInYear,
                    co2Method: $r->co2Method,
                    pollutantCategory: $r->pollutantCategory,
                    co2FullYearTariff: $segCo2Tariff,
                    pollutantsFullYearTariff: $segPollutantsTariff,
                    co2Due: $segCo2Due,
                    pollutantsDue: $segPollutantsDue,
                    appliedExemptions: array_map(
                        static fn ($e) => AppliedExemptionData::fromValueObject($e),
                        $r->appliedExemptions,
                    ),
                    appliedRuleCodes: $r->appliedRuleCodes,
                );

                $co2RawYear += $r->co2DueRaw;
                $pollutantsRawYear += $r->pollutantsDueRaw;
                $daysAssignedYear += $r->daysAssigned;
            }

            // Year summary · aggregates non-empty windows. The
            // header tariff is taken from the first useful window
            // (one with assigned contract days) so the UI does not
            // mislead with a pre-scission tariff when the contract
            // begins after the scission. The UI surfaces the
            // scission via `segments` when `len(segments) > 1`.
            $leaderResult = null;
            foreach ($breakdowns as $b) {
                if ($b->result->daysAssigned > 0) {
                    $leaderResult = $b->result;
                    break;
                }
            }
            // Degenerate case · no assigned contract day (pure LCD,
            // full exemption, contract outside effective year span).
            // Fall back to the first segment to keep coherent
            // structural values (method, category, tariffs).
            $firstResult = $leaderResult ?? $breakdowns[0]->result;
            $co2Tariff = round($firstResult->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
            $pollutantsTariff = round($firstResult->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
            $co2Due = round($co2RawYear, 2, PHP_ROUND_HALF_UP);
            $pollutantsDue = round($pollutantsRawYear, 2, PHP_ROUND_HALF_UP);
            $yearTotalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

            $appliedRuleCodes = array_keys($ruleCodesSet);
            $appliedRules = $this->loadRulesByCodes($year, $appliedRuleCodes);

            // Hypothetical « if requalified as LLD » fields stay
            // null here · enriched by
            // {@see ContractQueryService::findContractTaxBreakdown()}
            // through the R-YYYY-021 opt-out mechanism (see
            // {@see DeclarationAggregatorFactory}). The accurate
            // multi-VFC / multi-rule computation runs a second
            // pipeline pass scoped to the contract, not the
            // `tariff × days/365` approximation that ignored
            // scissions.
            $years[] = new ContractTaxYearBreakdownData(
                year: $year,
                daysInContractInYear: $daysInContractInYear,
                daysAssigned: $daysAssignedYear,
                daysInYear: $firstResult->daysInYear,
                co2Method: $firstResult->co2Method,
                pollutantCategory: $firstResult->pollutantCategory,
                co2FullYearTariff: $co2Tariff,
                pollutantsFullYearTariff: $pollutantsTariff,
                co2Due: $co2Due,
                pollutantsDue: $pollutantsDue,
                totalDue: $yearTotalDue,
                appliedExemptions: array_map(
                    static fn ($e) => AppliedExemptionData::fromValueObject($e),
                    array_values($exemptionsByCode),
                ),
                appliedRuleCodes: $appliedRuleCodes,
                appliedRules: $appliedRules,
                segments: $segmentsDto,
            );

            $totalRaw += $co2RawYear + $pollutantsRawYear;
        }

        return new ContractTaxBreakdownData(
            years: $years,
            totalDue: round($totalRaw, 2, PHP_ROUND_HALF_UP),
        );
    }

    private function buildCo2Explanation(
        ?VehicleFiscalCharacteristics $vfc,
        HomologationMethod $method,
        float $tariff,
        int $year,
    ): string {
        if ($vfc === null) {
            return 'Tarif annuel CO₂ calculé sans caractéristiques fiscales actives.';
        }

        $value = match ($method) {
            HomologationMethod::Wltp => $vfc->co2_wltp !== null ? "{$vfc->co2_wltp} g/km (WLTP)" : 'WLTP',
            HomologationMethod::Nedc => $vfc->co2_nedc !== null ? "{$vfc->co2_nedc} g/km (NEDC)" : 'NEDC',
            HomologationMethod::Pa => $vfc->taxable_horsepower !== null ? "{$vfc->taxable_horsepower} CV (puissance administrative)" : 'PA',
        };

        // Tariff at 0 € → exemption applies. The user has the list
        // of reasons in the « Exonérations applicables » section
        // below, so we avoid unrolling the computation again (would
        // misleadingly show « ... → 0 € » out of context).
        if ($tariff === 0.0) {
            return sprintf(
                '%s - exonérée pour ce véhicule (voir motif ci-dessous).',
                $value,
            );
        }

        return sprintf(
            '%s × barème CO₂ %d → tarif annuel %s.',
            $value,
            $year,
            number_format($tariff, 2, ',', ' ').' €',
        );
    }

    private function buildPollutantsExplanation(
        ?VehicleFiscalCharacteristics $vfc,
        PollutantCategory $category,
        float $tariff,
    ): string {
        if ($vfc === null) {
            return 'Tarif polluants calculé sans caractéristiques fiscales actives.';
        }

        $energy = $vfc->energy_source->label();
        $euro = $vfc->euro_standard?->label() ?? 'sans norme Euro renseignée';

        if ($tariff === 0.0) {
            return sprintf(
                '%s · %s → exonérée pour ce véhicule (voir motif ci-dessous).',
                $energy,
                $euro,
            );
        }

        return sprintf(
            '%s · %s → catégorie %s → tarif fixe annuel %s.',
            $energy,
            $euro,
            $category->label(),
            number_format($tariff, 2, ',', ' ').' €',
        );
    }

    /**
     * Vehicle annual cost split by using company with CO₂ /
     * pollutants / total separated. One entry per actually attributed
     * company; the consumer sorts (typically by descending days).
     *
     * @param  list<VehicleEvent>  $vehicleEvents
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    public function vehicleAnnualTaxBreakdownByCompany(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleEvents,
        int $year,
    ): array {
        $key = $vehicle->id.'|'.$year;

        return $this->vehicleAnnualTaxBreakdownByCompanyCache[$key] ??= $this->computeVehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            $contracts,
            $vehicleEvents,
            $year,
        );
    }

    /**
     * @param  list<VehicleEvent>  $vehicleEvents
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    private function computeVehicleAnnualTaxBreakdownByCompany(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleEvents,
        int $year,
    ): array {
        $rows = [];
        foreach ($contracts->pairsForVehicle($vehicle->id) as $companyId => $pairContracts) {
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, $pairContracts, $vehicleEvents, $year),
            );

            // Each line represents a taxpayer (a company using this
            // vehicle) · half-up rounding to the euro per CIBS
            // L. 131-1. Aligned with `companyAnnualTax`. Note that
            // `companyAnnualTaxBreakdownByVehicle` keeps centime
            // precision because its lines are per-vehicle subtotals
            // for one company, not declaratory totals.
            $taxCo2 = round($result->co2DueRaw, 0, PHP_ROUND_HALF_UP);
            $taxPollutants = round($result->pollutantsDueRaw, 0, PHP_ROUND_HALF_UP);

            $rows[] = [
                'companyId' => $companyId,
                'days' => $result->daysAssigned,
                'taxCo2' => $taxCo2,
                'taxPollutants' => $taxPollutants,
                'taxTotal' => $taxCo2 + $taxPollutants,
            ];
        }

        return $rows;
    }

    /**
     * Mirror of `vehicleAnnualTaxBreakdownByCompany` on the company
     * side · a company's fiscal detail split by used vehicle. Used by
     * the Fiscalité tab on the Company Show fiche and by
     * `DeclarationFiscalEngine` to compose the declaration snapshot.
     *
     * `appliedExemptions` propagated from the pipeline result lets the
     * declaration engine materialise the exemption reason on each
     * contract line (beyond the historical R-021 hardcode).
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexed by id
     * @param  array<int, list<VehicleEvent>>  $vehicleEventsByVehicleId
     * @return list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float, appliedExemptions: list<AppliedExemption>}>
     */
    public function companyAnnualTaxBreakdownByVehicle(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $vehicleEventsByVehicleId,
        int $year,
    ): array {
        $key = $companyId.'|'.$year;

        return $this->companyAnnualTaxBreakdownByVehicleCache[$key] ??= $this->computeCompanyAnnualTaxBreakdownByVehicle(
            $companyId,
            $vehiclesById,
            $contracts,
            $vehicleEventsByVehicleId,
            $year,
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<VehicleEvent>>  $vehicleEventsByVehicleId
     * @return list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float, appliedExemptions: list<AppliedExemption>}>
     */
    private function computeCompanyAnnualTaxBreakdownByVehicle(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $vehicleEventsByVehicleId,
        int $year,
    ): array {
        $rows = [];
        foreach ($contracts->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }

            $result = $this->pipeline->execute(
                $this->buildContext(
                    $vehicle,
                    $pairContracts,
                    $vehicleEventsByVehicleId[$vehicleId] ?? [],
                    $year,
                ),
            );

            $taxCo2 = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $taxPollutants = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

            $rows[] = [
                'vehicleId' => $vehicleId,
                'days' => $result->daysAssigned,
                'taxCo2' => $taxCo2,
                'taxPollutants' => $taxPollutants,
                'taxTotal' => round($taxCo2 + $taxPollutants, 2, PHP_ROUND_HALF_UP),
                'appliedExemptions' => $result->appliedExemptions,
            ];
        }

        return $rows;
    }

    /**
     * Total annual fiscal across the whole fleet (every
     * vehicle × company couple). Surfaced on the Dashboard as an
     * informative aggregate, not a declaratory amount.
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexed by id
     * @param  array<int, list<VehicleEvent>>  $vehicleEventsByVehicleId
     */
    public function fleetAnnualTax(
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $vehicleEventsByVehicleId,
        int $year,
    ): float {
        $key = (string) $year;

        return $this->fleetAnnualTaxCache[$key] ??= $this->computeFleetAnnualTax(
            $vehiclesById,
            $contracts,
            $vehicleEventsByVehicleId,
            $year,
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<VehicleEvent>>  $vehicleEventsByVehicleId
     */
    private function computeFleetAnnualTax(
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $vehicleEventsByVehicleId,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($contracts->vehicleCompanyPairs() as $pair) {
            $vehicle = $vehiclesById->get($pair['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $context = $this->buildContext(
                $vehicle,
                $pair['contracts'],
                $vehicleEventsByVehicleId[$pair['vehicleId']] ?? [],
                $year,
            );
            $cachedSegments = $this->vfcSegmentsCache[$vehicle->id.'|'.$year] ?? null;
            $result = $cachedSegments !== null
                ? $this->pipeline->executeWithPreloadedVfcSegments($context, $cachedSegments)
                : $this->pipeline->execute($context);
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  list<Contract>  $contractsForPair
     * @param  list<VehicleEvent>  $vehicleEvents
     */
    private function buildContext(
        Vehicle $vehicle,
        array $contractsForPair,
        array $vehicleEvents,
        int $year,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: $year,
            daysInYear: $this->yearContext->daysInYear($year),
            contractsForPair: $contractsForPair,
            vehicleUnavailabilitiesInYear: $vehicleEvents,
        );
    }

    /**
     * Memoized rule loading by codes for a year · key
     * `"{year}|{sortedCodes}"` so calls with the same codes in
     * different order share the cache entry.
     *
     * @param  list<string>  $codes
     * @return list<FiscalRuleListItemData>
     */
    private function loadRulesByCodes(int $year, array $codes): array
    {
        sort($codes);
        $key = $year.'|'.implode(',', $codes);

        return $this->rulesCache[$key] ??= $this->rulesQuery->listByCodesForYear($year, $codes);
    }

    /**
     * Memoized `PipelineResult` for the synthetic full-year
     * computation · purely a function of `(vehicleId, year)`.
     */
    private function fullYearPipelineResult(Vehicle $vehicle, int $year): PipelineResult
    {
        $key = $vehicle->id.'|'.$year;

        return $this->fullYearResultCache[$key] ??= $this->pipeline->execute(
            $this->buildContext($vehicle, [$this->fullYearSyntheticContract($year)], [], $year),
        );
    }

    /**
     * Synthetic non-persisted contract covering the whole year
     * (Jan 1 → Dec 31), used for the theoretical full-year tax. By
     * construction not LCD (length > 30 days, not a whole civil
     * month).
     */
    private function fullYearSyntheticContract(int $year): Contract
    {
        $contract = new Contract([
            'vehicle_id' => 0,
            'company_id' => 0,
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
            'contract_reference' => null,
            'contract_type' => ContractType::Lld,
            'notes' => null,
        ]);

        // Force the casts (Eloquent only casts attributes during DB
        // hydration / save).
        $contract->setRawAttributes([
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
            'contract_type' => ContractType::Lld->value,
        ], true);

        return $contract;
    }
}
