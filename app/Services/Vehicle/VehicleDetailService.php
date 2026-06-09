<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Vehicle\CurrentRentalData;
use App\Data\User\Vehicle\CurrentVehicleStatusData;
use App\Data\User\Vehicle\VehicleData;
use App\Data\User\Vehicle\VehicleOverviewData;
use App\Data\User\Vehicle\VehicleYearStatsData;
use App\Data\User\VehicleEvent\VehicleEventData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Detail service for the Vehicle Show page, extracted from
 * `VehicleQueryService` for SRP.
 *
 * Aggregates ·
 *   - identity + active fiscal characteristics
 *   - reverse-chronological VFC history
 *   - usage stats (Present KPI + Evolution)
 *   - initial `usageStats` (Usage & Allocation card), delegated to
 *     {@see VehicleAggregatesService::buildUsageStats()} so the
 *     algorithm matches the lazy endpoints
 *   - busyDates for the DateRangePicker in the unavailability modal
 *
 * Three temporal lenses ·
 *   - Present (`kpiYear` + `kpiStats`) · current calendar year, not
 *     mutable from the UI.
 *   - Evolution (`history[]`) · `[minYear..kpiYear-1]`, neutral zero
 *     rows included for years with no contract.
 *   - Exploration (`usageStats` + `selectedYear`) · driven by the
 *     shared year selector (Timeline + Breakdown + FullYearTax).
 */
final class VehicleDetailService
{
    public function __construct(
        private readonly VehicleEventReadRepositoryInterface $vehicleEventRepo,
        private readonly ContractQueryService $contracts,
        private readonly ContractReadRepositoryInterface $contractsRead,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
        private readonly RentalPriceCalculator $rentalPrice,
        // Coupling intent · `findVehicleData` must compose the initial
        // `usageStats` with the exact same algorithm as
        // `usageStatsForYear` (lazy endpoint). Reusing
        // `VehicleAggregatesService::buildUsageStats` avoids the ~70
        // duplicated lines of timeline + breakdown.
        private readonly VehicleAggregatesService $aggregates,
    ) {}

    /**
     * Slim always-eager base for the Show page (header on every tab +
     * Edit) · identity + active VFC + VFC history + events + year context
     * + pricings. The vehicle AND its events are loaded once by the caller
     * and threaded in, so the overview payload reuses the same events
     * Collection instead of re-querying it. The heavy overview-tab data
     * (usage/KPI/busyDates/history) is built by {@see overviewForVehicle()},
     * served only on the overview tab.
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels
     */
    public function findVehicleData(Vehicle $vehicle, Collection $vehicleEventModels): VehicleData
    {
        $vehicleEventDtos = $vehicleEventModels
            ->map(static fn (VehicleEvent $u): VehicleEventData => VehicleEventData::fromModel($u))
            ->values()
            ->all();

        $kpiYear = $this->availableYears->currentYear();

        return VehicleData::fromModel(
            $vehicle,
            $vehicleEventDtos,
            kpiYear: $kpiYear,
            selectedYear: $kpiYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Overview-tab-only payload · usage timeline + per-company breakdown,
     * current-year KPI, busy dates (unavailability modal) and multi-year
     * history. Eager only when `?tab=overview` (tab-gating); the other
     * tabs do not pay this when loaded directly. The events Collection is
     * loaded once by the caller and shared with the base DTO and across
     * the four computations here (incl. history, which previously leaked
     * onto every tab via `Inertia::defer`).
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels
     */
    public function overviewForVehicle(Vehicle $vehicle, Collection $vehicleEventModels): VehicleOverviewData
    {
        $kpiYear = $this->availableYears->currentYear();

        return new VehicleOverviewData(
            currentStatus: $this->buildCurrentStatus($vehicle, $vehicleEventModels, CarbonImmutable::today()),
            usageStats: $this->aggregates->buildUsageStats($vehicle, $kpiYear, $vehicleEventModels),
            kpiStats: $this->computeVehicleYearStats($vehicle, $kpiYear, $vehicleEventModels),
            kpiFiscalAvailable: in_array($kpiYear, $this->fiscalRules->registeredYears(), true),
            busyDates: $this->buildBusyDates($vehicle->id, $kpiYear),
            history: $this->historyForVehicleWithEvents($vehicle, $vehicleEventModels),
        );
    }

    /**
     * Current operational state as of `$today`: the event(s) spanning today
     * and the rental(s) active today (with drivers). The events are filtered
     * in PHP from the already-loaded Collection (no extra query); only the
     * active-rental lookup adds one bounded query (company + drivers
     * eager-loaded).
     *
     * A vehicle already out of the fleet (`exit_date <= today`) has no current
     * operational state: returns empty (the overview header surfaces the exit).
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels
     */
    public function buildCurrentStatus(
        Vehicle $vehicle,
        Collection $vehicleEventModels,
        CarbonImmutable $today,
    ): CurrentVehicleStatusData {
        if ($vehicle->exit_date !== null && $vehicle->exit_date->toImmutable()->lessThanOrEqualTo($today)) {
            return new CurrentVehicleStatusData(events: [], rentals: []);
        }

        $ongoingEvents = $vehicleEventModels
            ->filter(static fn (VehicleEvent $event): bool => $event->start_date->toImmutable()->lessThanOrEqualTo($today)
                && ($event->end_date === null || $event->end_date->toImmutable()->greaterThanOrEqualTo($today)))
            ->map(static fn (VehicleEvent $event): VehicleEventData => VehicleEventData::fromModel($event))
            ->values()
            ->all();

        $rentals = $this->contractsRead->findActiveForVehicleOnDate($vehicle->id, $today)
            ->map(static fn (Contract $contract): CurrentRentalData => CurrentRentalData::fromModel($contract))
            ->values()
            ->all();

        return new CurrentVehicleStatusData(events: $ongoingEvents, rentals: $rentals);
    }

    /**
     * Vehicle history · past fiscal years derived from the rule registry
     * (not from contracts), ordered DESC (newest first). Neutral rows
     * included for years with no contract so the user still sees the
     * theoretical full-year tax.
     *
     * @return list<VehicleYearStatsData>
     */
    public function historyForVehicle(Vehicle $vehicle): array
    {
        return $this->historyForVehicleWithEvents(
            $vehicle,
            $this->vehicleEventRepo->findForVehicle($vehicle->id),
        );
    }

    /**
     * History core from an already-loaded events Collection · lets
     * {@see overviewForVehicle()} share the single events load with the
     * usage/KPI computations instead of re-querying.
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels
     * @return list<VehicleYearStatsData>
     */
    private function historyForVehicleWithEvents(Vehicle $vehicle, Collection $vehicleEventModels): array
    {
        $kpiYear = $this->availableYears->currentYear();
        $registeredYears = $this->fiscalRules->registeredYears();
        $pastYears = array_values(array_filter(
            $registeredYears,
            static fn (int $y): bool => $y < $kpiYear,
        ));
        rsort($pastYears);

        $history = [];
        foreach ($pastYears as $year) {
            $history[] = $this->computeVehicleYearStats($vehicle, $year, $vehicleEventModels);
        }

        return $history;
    }

    /**
     * Yearly stats for one vehicle (used days, contract count, actual
     * tax, full-year tax). Drives both the Present KPI and every
     * history row.
     *
     * Tolerates a year without coded fiscal rules · returns
     * `actualTax = 0` and `fullYearTax = 0` rather than crashing the
     * page; the user still sees days and contract count.
     *
     * @param  Collection<int, VehicleEvent>  $vehicleEventModels  All-year unavailabilities preloaded.
     */
    private function computeVehicleYearStats(
        Vehicle $vehicle,
        int $year,
        Collection $vehicleEventModels,
    ): VehicleYearStatsData {
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleEvents = $vehicleEventModels->all();

        $daysUsed = 0;
        $contractsCount = 0;
        foreach ($contractsByPair->pairsForVehicle($vehicle->id) as $pairContracts) {
            foreach ($pairContracts as $contract) {
                $daysUsed += $contract->countDaysInYear($year);
                $contractsCount++;
            }
        }

        $actualTax = 0.0;
        $fullYearTax = 0.0;
        try {
            $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
                $vehicle,
                $contractsByPair,
                $vehicleEvents,
                $year,
            );
            foreach ($breakdown as $row) {
                $actualTax += (float) $row['taxTotal'];
            }
            $fullYearTax = $this->aggregator->vehicleFullYearTax($vehicle, $year);
        } catch (FiscalCalculationException) {
            // Year outside the fiscal registry · tax figures left at 0.
        }

        // Single-vehicle rental price on Show · the batched flavour is
        // unnecessary here (one vehicle = 2 acceptable SQL queries).
        $rentalCents = $this->rentalPrice->forVehicleAndYear($vehicle->id, $year);
        $rentalPrice = $rentalCents === null ? null : $rentalCents / 100;

        return new VehicleYearStatsData(
            year: $year,
            daysUsed: $daysUsed,
            contractsCount: $contractsCount,
            actualTax: round($actualTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: round($fullYearTax, 2, PHP_ROUND_HALF_UP),
            rentalPrice: $rentalPrice,
        );
    }

    /**
     * Flat list of ISO dates (Y-m-d) already busy with an active
     * contract on the vehicle for the year. Feeds the unavailability
     * modal's `DateRangePicker` to grey out unselectable days.
     *
     * Bounded to `[01-01-Y, 31-12-Y]` · contracts outside the window
     * do not block the UI (the Action validates again before writing
     * if the user opens an overlapping range).
     *
     * @return list<string>
     */
    private function buildBusyDates(int $vehicleId, int $year): array
    {
        return $this->contracts->findDatesForVehicleInRange(
            $vehicleId,
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
        );
    }
}
