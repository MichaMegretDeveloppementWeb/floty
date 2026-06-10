<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\User\Billing\MonthlyBillingBreakdownData;
use App\Data\User\Vehicle\VehicleCompanyUsageData;
use App\Data\User\Vehicle\VehicleFiscalCharacteristicsData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\Data\User\Vehicle\VehicleFullYearTaxSegmentData;
use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Data\User\Vehicle\VehicleWeekSegmentData;
use App\Data\User\Vehicle\VehicleWeekUsageData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Fiscal\SegmentBoundaryCause;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-vehicle yearly aggregates for the Show tabs and lazy JSON
 * endpoints (extracted from `VehicleQueryService` to respect SRP).
 *
 * Bundles the three per-year façades consumed by the vehicle fiche ·
 *   - `billingForYear` · Billing tab (monthly billing recap).
 *   - `usageStatsForYear` · JSON endpoint for the Usage & Allocation
 *     card on the Overview tab.
 *   - `fullYearBreakdownForYear` · JSON endpoint for the Fiscality tab.
 *
 * Also centralises the private helpers that compose
 * `VehicleUsageStatsData` (weekly timeline, breakdown by company,
 * fallback for years without coded rules), reused by
 * `VehicleDetailService::findVehicleData` through `buildUsageStats()`.
 */
final class VehicleAggregatesService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleEventReadRepositoryInterface $vehicleEventRepo,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly BillingBreakdownService $billingBreakdown,
    ) {}

    /**
     * Monthly billing recap for a vehicle and a year. Separating
     * `BillingBreakdownService` aggregation from the main
     * `findVehicleData()` flow lets the UI carry an independent year
     * selector.
     */
    public function billingForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
    {
        return $this->billingBreakdown->byVehicleForYear($vehicleId, $year);
    }

    /**
     * Lazy endpoint · recomputes `VehicleUsageStatsData` for any year
     * in scope, called by `useYearLazy` when the Overview-tab Usage &
     * Allocation year selector changes.
     *
     * Tolerates a year without coded fiscal rules · timeline and raw
     * days stay intact, tax figures fall back to 0 with a neutral
     * full-year breakdown.
     */
    public function usageStatsForYear(Vehicle $vehicle, int $year): VehicleUsageStatsData
    {
        $vehicleEventModels = $this->vehicleEventRepo->findForVehicle($vehicle->id);

        return $this->buildUsageStats($vehicle, $year, $vehicleEventModels);
    }

    /**
     * Lazy endpoint · recomputes `VehicleFullYearTaxBreakdownData` for
     * any year. Returns a neutral DTO (tariffs 0 + « rules not
     * implemented » message) when the year has no coded rules.
     */
    public function fullYearBreakdownForYear(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        try {
            return $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
        } catch (FiscalCalculationException) {
            return $this->emptyFullYearBreakdown($vehicle, $year);
        }
    }

    /**
     * Public composition of `VehicleUsageStatsData` for the initial
     * mount of the Show page (called by
     * `VehicleDetailService::findVehicleData`).
     *
     * The Detail call site already loads the vehicle and the
     * unavailabilities in bulk to share with other calculations; both
     * are passed in to avoid reloading (saves two SQL queries).
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels
     */
    public function buildUsageStats(Vehicle $vehicle, int $year, Collection $vehicleEventModels): VehicleUsageStatsData
    {
        $daysInYear = $this->yearContext->daysInYear($year);
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleEvents = $vehicleEventModels->all();
        $weeklyMap = $this->contracts->loadVehicleWeeklyBreakdown($vehicle->id, $year);
        $vehicleEventDaysByWeek = $this->computeVehicleEventDaysByWeek($vehicleEventModels, $year);

        // Wrap fiscal computation so a year outside the rule registry
        // does not break the page · timeline and raw days remain
        // displayable, tax figures fall back to 0 with a neutral
        // full-year breakdown.
        try {
            $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
                $vehicle,
                $contractsByPair,
                $vehicleEvents,
                $year,
            );
            $fullYearBreakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
        } catch (FiscalCalculationException) {
            $breakdown = $this->fallbackBreakdownByCompany($contractsByPair, $vehicle->id);
            $fullYearBreakdown = $this->emptyFullYearBreakdown($vehicle, $year);
        }

        $companyIds = $this->collectCompanyIds($breakdown, $weeklyMap);
        $companiesById = $this->companies->findByIdsIndexed($companyIds);

        // Operational days actually rented per company, distinct from the
        // taxable days that drive the tax columns: an exonerated rental (LCD)
        // shows its real days with 0 tax.
        $operationalDaysByCompany = [];
        foreach ($contractsByPair->pairsForVehicle($vehicle->id) as $companyId => $pairContracts) {
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += $contract->countDaysInYear($year);
            }
            $operationalDaysByCompany[$companyId] = $days;
        }

        usort(
            $breakdown,
            static fn (array $a, array $b): int => ($operationalDaysByCompany[$b['companyId']] ?? 0)
                <=> ($operationalDaysByCompany[$a['companyId']] ?? 0),
        );

        $companies = [];
        $totalDays = 0;
        $totalTax = 0.0;
        foreach ($breakdown as $row) {
            $company = $companiesById->get($row['companyId']);
            if ($company === null) {
                continue;
            }
            $daysUsed = $operationalDaysByCompany[$row['companyId']] ?? 0;
            $proratoPercent = $daysInYear > 0
                ? round($daysUsed / $daysInYear * 100, 1)
                : 0.0;

            $companies[] = new VehicleCompanyUsageData(
                companyId: $company->id,
                shortCode: $company->short_code,
                legalName: $company->legal_name,
                color: $company->color,
                daysUsed: $daysUsed,
                proratoPercent: $proratoPercent,
                taxCo2: $row['taxCo2'],
                taxPollutants: $row['taxPollutants'],
                taxTotal: $row['taxTotal'],
            );
            $totalDays += $daysUsed;
            $totalTax += $row['taxTotal'];
        }

        return new VehicleUsageStatsData(
            fiscalYear: $year,
            daysInYear: $daysInYear,
            daysUsedThisYear: $totalDays,
            actualTaxThisYear: round($totalTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: $fullYearBreakdown->total,
            dailyTaxRate: $daysInYear > 0
                ? round($fullYearBreakdown->total / $daysInYear, 2, PHP_ROUND_HALF_UP)
                : 0.0,
            companies: $companies,
            weeklyBreakdown: $this->buildWeeklyBreakdown($weeklyMap, $vehicleEventDaysByWeek, $companiesById, $year),
            fullYearTaxBreakdown: $fullYearBreakdown,
        );
    }

    /**
     * Composes a per-company breakdown from contracts only (days,
     * without fiscal computation). Used as a fallback when the fiscal
     * pipeline is unavailable for the year · the « Jours » column is
     * informative, tax columns are 0.
     *
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    private function fallbackBreakdownByCompany(ContractsByPair $contractsByPair, int $vehicleId): array
    {
        $rows = [];
        foreach ($contractsByPair->pairsForVehicle($vehicleId) as $companyId => $pairContracts) {
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += $contract->countDaysInYear($contract->start_date->year);
            }
            $rows[] = [
                'companyId' => $companyId,
                'days' => $days,
                'taxCo2' => 0.0,
                'taxPollutants' => 0.0,
                'taxTotal' => 0.0,
            ];
        }

        return $rows;
    }

    /**
     * Neutral `VehicleFullYearTaxBreakdownData` · zero tariffs and
     * explicit messages for years lacking coded fiscal rules. Enums
     * `co2Method` / `pollutantCategory` come from the current VFC
     * (defaults · WLTP / Category1).
     */
    private function emptyFullYearBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $current = $vehicle->fiscalCharacteristics
            ->firstWhere(static fn ($vfc): bool => $vfc->effective_to === null);

        $message = sprintf('Règles fiscales %d non implémentées.', $year);

        // Unsupported year → no pipeline execution, no computed
        // segments. Expose the current VFC (if any) as a single segment
        // covering the year with zero tariffs and dues, so UI
        // traceability survives without lying about a computation that
        // did not happen.
        $taxSegments = [];
        if ($current !== null) {
            $taxSegments[] = new VehicleFullYearTaxSegmentData(
                effectiveFromInYear: sprintf('%04d-01-01', $year),
                effectiveToInYear: sprintf('%04d-12-31', $year),
                daysInSegment: $this->yearContext->daysInYear($year),
                boundaryCause: SegmentBoundaryCause::Initial,
                vfc: VehicleFiscalCharacteristicsData::fromModel($current),
                co2Method: $current->homologation_method,
                co2FullYearTariff: 0.0,
                co2Explanation: $message,
                co2Due: 0.0,
                pollutantCategory: $current->pollutant_category,
                pollutantsFullYearTariff: 0.0,
                pollutantsExplanation: $message,
                pollutantsDue: 0.0,
                appliedExemptions: [],
                appliedRuleCodes: [],
            );
        }

        return new VehicleFullYearTaxBreakdownData(
            daysInYear: $this->yearContext->daysInYear($year),
            total: 0.0,
            appliedExemptions: [],
            appliedRuleCodes: [],
            appliedRules: [],
            taxSegments: $taxSegments,
        );
    }

    /**
     * Collects every companyId referenced by the fiscal breakdown and
     * the weekly timeline so no Eloquent lookup goes missing (e.g. a
     * seeded week with zero retained days because of a fourrière).
     *
     * @param  list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>  $breakdown
     * @param  array<int, array<int, int>>  $weeklyMap
     * @return list<int>
     */
    private function collectCompanyIds(array $breakdown, array $weeklyMap): array
    {
        $ids = [];
        foreach ($breakdown as $row) {
            $ids[$row['companyId']] = true;
        }
        foreach ($weeklyMap as $companies) {
            foreach (array_keys($companies) as $companyId) {
                $ids[$companyId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Composes the 52-53 weekly entries (one per ISO week) for the
     * visual timeline. Empty weeks are materialised with empty
     * segments and zero totalDays.
     *
     * VehicleEvent days are split into
     * `reductiveVehicleEventDays` (R-2024-008) and
     * `nonReductiveUnavailabilityDays`; the timeline overlays them
     * differently (pink vs slate). ADR-0019 allows
     * unavailability/contract cohabitation, so we no longer clamp to
     * `7 - totalDays` · a fourrière must be visible even when the
     * contract covers the whole week.
     *
     * @param  array<int, array<int, int>>  $weeklyMap  weekNumber → companyId → days
     * @param  array<int, array{reductive: int, nonReductive: int}>  $vehicleEventDaysByWeek
     * @param  Collection<int, Company>  $companiesById
     * @return list<VehicleWeekUsageData>
     */
    private function buildWeeklyBreakdown(
        array $weeklyMap,
        array $vehicleEventDaysByWeek,
        Collection $companiesById,
        int $year,
    ): array {
        $weeksInYear = (int) Carbon::create($year, 12, 28)->isoWeeksInYear;

        $rows = [];
        for ($week = 1; $week <= $weeksInYear; $week++) {
            $segments = [];
            $totalDays = 0;
            foreach (($weeklyMap[$week] ?? []) as $companyId => $days) {
                $company = $companiesById->get($companyId);
                if ($company === null) {
                    continue;
                }
                $segments[] = new VehicleWeekSegmentData(
                    companyId: $company->id,
                    shortCode: $company->short_code,
                    color: $company->color,
                    days: $days,
                );
                $totalDays += $days;
            }
            usort(
                $segments,
                static fn (VehicleWeekSegmentData $a, VehicleWeekSegmentData $b): int => $a->companyId <=> $b->companyId,
            );

            $weekVehicleEvent = $vehicleEventDaysByWeek[$week] ?? ['reductive' => 0, 'nonReductive' => 0];

            $rows[] = new VehicleWeekUsageData(
                weekNumber: $week,
                segments: $segments,
                totalDays: $totalDays,
                reductiveVehicleEventDays: $weekVehicleEvent['reductive'],
                nonReductiveUnavailabilityDays: $weekVehicleEvent['nonReductive'],
            );
        }

        return $rows;
    }

    /**
     * Counts `weekNumber → {reductive, nonReductive}` from the already
     * loaded Collection. A date covered by two overlapping events
     * (allowed by ADR-0019) is counted once, with the fiscally
     * reductive event taking priority (the most informative signal
     * for the UI).
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels
     * @return array<int, array{reductive: int, nonReductive: int}> weekNumber → totals per type
     */
    private function computeVehicleEventDaysByWeek(Collection $vehicleEventModels, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        /** @var array<int, array<string, 'reductive'|'nonReductive'>> $byWeekDays */
        $byWeekDays = [];
        foreach ($vehicleEventModels as $row) {
            // Informative unavailability axis only: a custom "other" event
            // with the flag off never counts as an unavailability day.
            if (! $row->implies_unavailability) {
                continue;
            }
            if ($row->start_date->greaterThan($yearEnd)) {
                continue;
            }
            if ($row->end_date !== null && $row->end_date->lessThan($yearStart)) {
                continue;
            }

            $isReductive = $row->has_fiscal_impact;
            $start = $row->start_date->greaterThan($yearStart) ? $row->start_date : $yearStart;
            $end = $row->end_date === null || $row->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $row->end_date;

            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $week = (int) $cursor->isoWeek;
                    $date = $cursor->toDateString();
                    $existing = $byWeekDays[$week][$date] ?? null;

                    if ($existing !== 'reductive') {
                        $byWeekDays[$week][$date] = $isReductive ? 'reductive' : 'nonReductive';
                    }
                }
                $cursor = $cursor->addDay();
            }
        }

        $byWeek = [];
        foreach ($byWeekDays as $week => $dates) {
            $reductive = 0;
            $nonReductive = 0;
            foreach ($dates as $kind) {
                if ($kind === 'reductive') {
                    $reductive++;
                } else {
                    $nonReductive++;
                }
            }
            $byWeek[$week] = ['reductive' => $reductive, 'nonReductive' => $nonReductive];
        }
        ksort($byWeek);

        return $byWeek;
    }
}
