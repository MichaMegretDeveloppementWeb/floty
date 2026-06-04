<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Dashboard\DashboardHistoryPointData;
use App\Data\User\Dashboard\DashboardKpiData;
use App\Data\User\Dashboard\DashboardKpiRecettesData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes the data blocks of the redesigned Dashboard along the three
 * lenses (Present / Evolution / Exploration) and the four pivot KPIs
 * (vehicle-days, active contracts, taxes due, fleet occupancy rate).
 *
 * "YTD" means January 1st through today. The Y-1 comparison uses the
 * same `[Jan 1, same day-month a year earlier]` window so both periods
 * stay comparable.
 *
 * Taxes YTD approximation · the fiscal tax is an annual computation,
 * not a daily cumulative. YTD is approximated as
 * `fleetAnnualTax × (elapsedDays / daysInYear)`. Imperfect (scales are
 * not linear) but enough to give a consistent trend on the Dashboard
 * across Y and Y-1. For the exact figure the user opens the company or
 * vehicle fiche.
 */
final class DashboardStatsService
{
    /**
     * Maximum number of bars in the « Évolution » chart. When the
     * dynamic contract scope reaches further, we keep the N most
     * recent years for visual clarity and to cap the fiscal pipeline
     * cost.
     */
    private const HISTORY_MAX_YEARS = 4;

    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
        private readonly BillingBreakdownService $billingBreakdown,
        private readonly DashboardPendingTasksAggregator $pendingTasksAggregator,
    ) {}

    /**
     * Four "Present" fiscal KPIs for the current calendar year. The
     * Y-1 comparison was retired · multi-year history (loaded
     * on-demand through a button) carries that comparison. The fiscal
     * pipeline now runs only once on Dashboard mount, halving the cost.
     */
    public function computeKpisFiscal(int $year): DashboardKpiData
    {
        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext($year, $year);

        $current = $this->computePeriodMetrics($year, $today, $context);

        return new DashboardKpiData(
            year: $year,
            joursVehicule: $current['joursVehicule'],
            contracts: $current['contracts'],
            contractsActiveNow: $current['contractsActiveNow'],
            taxesDues: $current['taxesDues'],
            tauxOccupation: $current['tauxOccupation'],
        );
    }

    /**
     * Rental-revenue card, served as a dedicated `Inertia::defer`
     * group so the fiscal KPI grid can mount without waiting on the
     * ~60 queries of `BillingBreakdownService::byCompanyForYear`.
     * Semantics · full year (Jan-Dec), independent of today.
     */
    public function computeKpisRecettes(int $year): DashboardKpiRecettesData
    {
        $totals = $this->billingBreakdown->totalRecettesForYears([$year]);

        return new DashboardKpiRecettesData(
            year: $year,
            recettesLocativesCents: $totals[$year],
        );
    }

    /**
     * Memoised context for Dashboard computations over a year range.
     * Pre-loads contracts grouped by `(vehicle, company)` per year,
     * the relevant vehicles, and the unavailabilities. ~3 fixed SQL
     * queries instead of `~2N + 3N` with a year-by-year approach.
     * Rental revenues are no longer pre-fetched here · they get a
     * dedicated defer through {@see computeKpisRecettes()}.
     */
    private function buildScopeContext(int $fromYear, int $toYear): DashboardScopeContext
    {
        $contractsByYear = $this->contracts->loadContractsByPairForYearRange($fromYear, $toYear);

        $allVehicleIds = [];
        foreach ($contractsByYear as $pair) {
            foreach ($pair->vehicleCompanyPairs() as $vc) {
                $allVehicleIds[$vc['vehicleId']] = true;
            }
        }
        $vehicleIdList = array_keys($allVehicleIds);

        $vehiclesById = $vehicleIdList === []
            ? new Collection
            : $this->vehicles->findByIdsIndexed($vehicleIdList);

        $vehicleEventsByVehicleId = $vehicleIdList === []
            ? []
            : $this->contracts->loadVehicleEventsByVehicle($vehicleIdList);

        return new DashboardScopeContext(
            contractsByYear: $contractsByYear,
            vehiclesById: $vehiclesById,
            vehicleEventsByVehicleId: $vehicleEventsByVehicleId,
        );
    }

    /**
     * Year scope for the Evolution chart, truncated to the N most recent
     * (see {@see HISTORY_MAX_YEARS}).
     *
     * Uses the same union range as the Planning year selector (years with
     * coded fiscal rules + years holding contracts + the current calendar
     * year, via {@see YearScopeData::fromResolverAndRegistry()}), so the
     * chart spans every fiscally-defined year even on a fresh instance
     * with no contract yet. The current year is always in range by
     * construction.
     *
     * @return list<int>
     */
    private function historyYearScope(): array
    {
        $scope = YearScopeData::fromResolverAndRegistry(
            $this->availableYears,
            $this->fiscalRules,
        )->availableYears;

        if (count($scope) > self::HISTORY_MAX_YEARS) {
            $scope = array_slice($scope, -self::HISTORY_MAX_YEARS);
        }

        return $scope;
    }

    /**
     * « Jours-véhicule » history · 1 contracts query × scope, pure
     * arithmetic sum (`countDaysInYearUpTo`). No fiscal pipeline, no
     * vehicles, no unavailabilities · the cheapest dimension and
     * default tab at Dashboard mount.
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryJoursVehicule(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();
        $today = CarbonImmutable::today();
        $contractsByYear = $this->contracts->loadContractsByPairForYearRange(min($scope), max($scope));

        $points = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            $endDateStr = $isCurrent
                ? $today->toDateString()
                : sprintf('%04d-12-31', $year);

            $jours = 0;
            $contracts = $contractsByYear[$year] ?? new ContractsByPair([]);
            foreach ($contracts->vehicleCompanyPairs() as $pair) {
                foreach ($pair['contracts'] as $contract) {
                    $jours += $contract->countDaysInYearUpTo($year, $endDateStr);
                }
            }

            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $isCurrent,
                value: $jours,
            );
        }

        return $points;
    }

    /**
     * « Locations » history · counts the contracts with activity over
     * `[Jan 1, upToDate]`. Same contracts query as
     * {@see computeHistoryJoursVehicule()}, no pipeline.
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryContracts(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();
        $today = CarbonImmutable::today();
        $contractsByYear = $this->contracts->loadContractsByPairForYearRange(min($scope), max($scope));

        $points = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            $endDateStr = $isCurrent
                ? $today->toDateString()
                : sprintf('%04d-12-31', $year);

            $contractIds = [];
            $contracts = $contractsByYear[$year] ?? new ContractsByPair([]);
            foreach ($contracts->vehicleCompanyPairs() as $pair) {
                foreach ($pair['contracts'] as $contract) {
                    if ($contract->start_date->toDateString() <= $endDateStr) {
                        $contractIds[$contract->id] = true;
                    }
                }
            }

            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $isCurrent,
                value: count($contractIds),
            );
        }

        return $points;
    }

    /**
     * « Taxes dues » history · YTD for the current year, full year for
     * earlier ones. Expensive dimension · runs the fiscal pipeline
     * N pairs × scope. Served via `Inertia::optional` (loaded on tab
     * click).
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryTaxes(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();
        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext(min($scope), max($scope));

        $points = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            $endDate = $isCurrent ? $today : CarbonImmutable::create($year, 12, 31);

            $taxesAnnuelles = $this->safeFleetAnnualTax($context->contractsForYear($year), $year, $context);
            $daysInYear = $this->yearContext->daysInYear($year);
            $daysElapsed = $endDate->dayOfYear;
            // YTD prorata rounded to the euro, aligned with the fiscal
            // aggregates (CIBS L. 131-1).
            $taxesDues = $daysInYear > 0 ? round($taxesAnnuelles * $daysElapsed / $daysInYear, 0, PHP_ROUND_HALF_UP) : 0.0;

            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $isCurrent,
                value: $taxesDues,
            );
        }

        return $points;
    }

    /**
     * « Recettes locatives » history · full year (Jan-Dec), cross
     * companies. Batched SQL via
     * {@see BillingBreakdownService::totalRecettesForYears()} (3-4
     * fixed queries regardless of scope). Served via
     * `Inertia::optional`.
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryRecettes(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();

        $recettesByYear = $this->billingBreakdown->totalRecettesForYears($scope);

        $points = [];
        foreach ($scope as $year) {
            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $year === $currentYear,
                value: $recettesByYear[$year],
            );
        }

        return $points;
    }

    /**
     * Fleet-wide pending tasks. Delegates to
     * {@see DashboardPendingTasksAggregator}, which gathers pending
     * items across active companies via the existing resolvers
     * ({@see PendingDeclarationsResolver},
     * {@see PendingInvoicesResolver}).
     */
    public function computePendingTasks(): DashboardPendingTasksData
    {
        return $this->pendingTasksAggregator->aggregate();
    }

    /**
     * Year metrics up to a reference date (Jan 1 → `$upToDate`).
     * Reused by the Present KPI computation.
     *
     * When `$context` is provided, every read comes from the bulk
     * prefetched pivot · zero extra query. Falls back to standalone
     * loads otherwise.
     *
     * @return array{joursVehicule: int, contracts: int, contractsActiveNow: int, taxesDues: float, tauxOccupation: float}
     */
    private function computePeriodMetrics(int $year, CarbonImmutable $upToDate, ?DashboardScopeContext $context = null): array
    {
        $contractsByPair = $context !== null
            ? $context->contractsForYear($year)
            : $this->contracts->loadContractsByPair($year);
        $upToDateString = $upToDate->toDateString();
        $todayString = CarbonImmutable::today()->toDateString();

        // YTD vehicle-days · pure arithmetic via `countDaysInYearUpTo`,
        // strictly equivalent to filtering an expanded list by
        // `<= upToDate`. Big CPU saver on history (8 years × N pairs)
        // since we no longer allocate up to 365 strings per contract.
        $joursVehicule = 0;
        // Every contract that overlaps `[Jan 1, upToDate]` counts
        // once (deduped across pairs by id).
        $contractsTotalIds = [];
        // « Active now » subset · `start_date <= today <= end_date`.
        // Only used by the Present lens (no Y-1, no history).
        $contractsActiveNowIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            foreach ($pair['contracts'] as $contract) {
                $joursVehicule += $contract->countDaysInYearUpTo($year, $upToDateString);
                if ($contract->start_date->toDateString() <= $upToDateString) {
                    $contractsTotalIds[$contract->id] = true;
                }
                if ($contract->start_date->toDateString() <= $todayString
                    && $contract->end_date->toDateString() >= $todayString
                ) {
                    $contractsActiveNowIds[$contract->id] = true;
                }
            }
        }
        $contractsCount = count($contractsTotalIds);
        $contractsActiveNowCount = count($contractsActiveNowIds);

        // YTD taxes · linear approximation of the annual tax (see
        // class PHPDoc).
        $taxesAnnuelles = $this->safeFleetAnnualTax($contractsByPair, $year, $context);
        $daysInYear = $this->yearContext->daysInYear($year);
        $daysElapsed = $upToDate->dayOfYear;
        $taxesDues = $daysInYear > 0 ? round($taxesAnnuelles * $daysElapsed / $daysInYear, 2) : 0.0;

        // YTD occupancy · realised vehicle-days / theoretical.
        // Theoretical = currently active vehicles × elapsed days. We
        // use the current count rather than the daily average · good
        // enough for the trend.
        $vehiclesActifs = $this->vehicles->countActive();
        $theoriques = $vehiclesActifs * $daysElapsed;
        $tauxOccupation = $theoriques > 0
            ? round(($joursVehicule / $theoriques) * 100, 1)
            : 0.0;

        return [
            'joursVehicule' => $joursVehicule,
            'contracts' => $contractsCount,
            'contractsActiveNow' => $contractsActiveNowCount,
            'taxesDues' => $taxesDues,
            'tauxOccupation' => $tauxOccupation,
        ];
    }

    /**
     * Wraps `fleetAnnualTax` and tolerates years without registered
     * fiscal rules · returns 0.0 when the year has no boot.
     *
     * Always prewarms VFC segments in a single SQL query before the
     * pipeline runs. Without the prewarm, `fleetAnnualTax` issues one
     * VFC query per vehicle (a brutal N+1 · ~70 queries for 30
     * vehicles × 2 years). The pipeline picks up the cache via the
     * `executeWithPreloadedVfcSegments` branch. Strictly equivalent to
     * the non-prewarmed call · covered by the
     * `prewarm_vfc_segments_equivalent_*` tests. Idempotent.
     */
    private function safeFleetAnnualTax(ContractsByPair $contractsByPair, int $year, ?DashboardScopeContext $context = null): float
    {
        try {
            $vehicleIds = [];
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                $vehicleIds[$pair['vehicleId']] = true;
            }
            $vehicleIdList = array_keys($vehicleIds);

            if ($vehicleIdList === []) {
                return 0.0;
            }

            if ($context !== null) {
                // `fleetAnnualTax` iterates the pivot's
                // `vehicleCompanyPairs()` and only uses the vehicles
                // that are actually present, so a superset
                // `$context->vehiclesById` is safe (the extras are
                // ignored).
                $vehiclesById = $context->vehiclesById;
                $vehicleEventsByVehicleId = $context->vehicleEventsByVehicleId;
            } else {
                $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
                $vehicleEventsByVehicleId = $this->contracts->loadVehicleEventsByVehicle($vehicleIdList);
            }

            // Prewarm only the vehicles actually present in the pivot
            // to avoid unnecessary work on a multi-year superset.
            $relevantVehicles = $vehiclesById->only($vehicleIdList);
            $this->aggregator->prewarmVfcSegmentsForVehicles($relevantVehicles, $year);

            return $this->aggregator->fleetAnnualTax(
                $vehiclesById,
                $contractsByPair,
                $vehicleEventsByVehicleId,
                $year,
            );
        } catch (FiscalCalculationException) {
            return 0.0;
        }
    }
}
