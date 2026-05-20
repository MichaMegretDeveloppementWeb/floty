<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Data\User\Vehicle\VehicleData;
use App\Data\User\Vehicle\VehicleYearStatsData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
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
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly UnavailabilityReadRepositoryInterface $unavailabilityRepo,
        private readonly ContractQueryService $contracts,
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
     * Full vehicle representation for the Show page · identity +
     * active VFC + reverse-chronological VFC history + usage stats.
     * Throws `ModelNotFoundException` (404 rendered by Laravel) when
     * the id does not exist.
     */
    public function findVehicleData(int $id): VehicleData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($id);

        // Load the raw Collection once and propagate it to
        // `buildUsageStats` and the timeline DTO composition · the
        // previous version triggered the same `findForVehicle` query
        // twice.
        $unavailabilityModels = $this->unavailabilityRepo->findForVehicle($vehicle->id);
        $unavailabilityDtos = $unavailabilityModels
            ->map(static fn (Unavailability $u): UnavailabilityData => UnavailabilityData::fromModel($u))
            ->values()
            ->all();

        $kpiYear = $this->availableYears->currentYear();
        $kpiStats = $this->computeVehicleYearStats($vehicle, $kpiYear, $unavailabilityModels);
        $kpiFiscalAvailable = in_array(
            $kpiYear,
            $this->fiscalRules->registeredYears(),
            true,
        );

        // History is served via `Inertia::defer` from
        // VehicleController::show so the mount is not blocked by N
        // fiscal pipelines. See `historyForVehicle()` below.

        // Exploration · `usageStats` initialised on the current year.
        // Other years are fetched on demand by the frontend via the
        // lazy endpoints with client-side cache (`useYearLazy`).
        $initialYear = $kpiYear;

        return VehicleData::fromModel(
            $vehicle,
            $this->aggregates->buildUsageStats($vehicle, $initialYear, $unavailabilityModels),
            $unavailabilityDtos,
            $this->buildBusyDates($vehicle->id, $initialYear),
            kpiYear: $kpiYear,
            kpiStats: $kpiStats,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            selectedYear: $initialYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Vehicle history · past fiscal years derived from the rule
     * registry (not from contracts), ordered DESC (newest first).
     * Neutral rows included for years with no contract so the user
     * still sees the theoretical full-year tax. Served via
     * `Inertia::defer` from Show so the mount is not blocked on N
     * fiscal pipelines (~100-150 ms cold saved depending on scope
     * depth).
     *
     * @return list<VehicleYearStatsData>
     */
    public function historyForVehicle(int $id): array
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($id);
        $unavailabilityModels = $this->unavailabilityRepo->findForVehicle($vehicle->id);

        $kpiYear = $this->availableYears->currentYear();
        $registeredYears = $this->fiscalRules->registeredYears();
        $pastYears = array_values(array_filter(
            $registeredYears,
            static fn (int $y): bool => $y < $kpiYear,
        ));
        rsort($pastYears);

        $history = [];
        foreach ($pastYears as $year) {
            $history[] = $this->computeVehicleYearStats($vehicle, $year, $unavailabilityModels);
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
     * @param  Collection<int, Unavailability>  $unavailabilityModels  All-year unavailabilities preloaded.
     */
    private function computeVehicleYearStats(
        Vehicle $vehicle,
        int $year,
        Collection $unavailabilityModels,
    ): VehicleYearStatsData {
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleUnavailabilities = $unavailabilityModels->all();

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
                $vehicleUnavailabilities,
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
