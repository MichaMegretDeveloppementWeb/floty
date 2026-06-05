<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Models\Contract;
use App\Models\VehicleYearlyPricing;
use Carbon\CarbonImmutable;

/**
 * Façade exposing rental-price calculations to Index and Show screens.
 *
 *   - `forContract(contractId)` · single contract split by civil month,
 *     OptimalRateBreakdown per month, then summed.
 *   - `forVehicleAndYear(vehicleId, year)` · vehicle over a year
 *     (cross-companies sum of monthly figures).
 *   - `forCompanyAndYear(companyId, year)` · company over a year (sum
 *     of the 12 monthly invoices).
 *
 * All methods return `int|null` cents; `null` signals a missing yearly
 * pricing for at least one needed (vehicle × year) pair (UI renders « · »).
 *
 * Batch endpoints (`forContracts`, `forVehiclesAndYear`) collapse the
 * N+1 pattern down to 2 SQL queries (pricings + contracts) and apply
 * `OptimalRateBreakdown` in memory.
 */
final readonly class RentalPriceCalculator
{
    public function __construct(
        private ContractReadRepositoryInterface $contractRepo,
        private VehicleYearlyPricingReadRepositoryInterface $pricingRepo,
    ) {}

    /**
     * Rent for a single contract · split by civil month (with `exit_date`
     * clipping per ADR-0018), OptimalRateBreakdown per month, summed.
     */
    public function forContract(int $contractId): ?int
    {
        $contract = $this->contractRepo->findByIdWithRelations($contractId);

        if ($contract === null) {
            return null;
        }

        return $this->forContractModel($contract);
    }

    /**
     * Variant that takes an already-loaded contract model, avoiding a
     * repository round-trip when the caller already has it (typically
     * paginated indexes). Pricings can be passed in to skip per-call
     * SQL when prefetched in batch.
     *
     * @param  array<string, VehicleYearlyPricing>|null  $pricingsByVehicleYearKey
     *                                                                              Key · `"{vehicleId}.{year}"`. When provided, no SQL is issued.
     */
    public function forContractModel(Contract $contract, ?array $pricingsByVehicleYearKey = null): ?int
    {
        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        $exitDate = $contract->vehicle?->exit_date;
        if ($exitDate !== null) {
            $exitImmutable = $exitDate->toImmutable();
            if ($exitImmutable->isBefore($start)) {
                return 0;
            }
            if ($exitImmutable->isBefore($end)) {
                $end = $exitImmutable;
            }
        }

        $total = 0;
        $cursor = $start->startOfMonth();

        while (! $cursor->isAfter($end)) {
            $monthStart = $cursor;
            $monthEnd = $cursor->endOfMonth();

            $clipStart = $start->isAfter($monthStart) ? $start : $monthStart;
            $clipEnd = $end->isBefore($monthEnd) ? $end : $monthEnd;

            if ($clipStart->isAfter($clipEnd)) {
                $cursor = $cursor->addMonth();

                continue;
            }

            $daysUsed = (int) $clipStart->diffInDays($clipEnd) + 1;

            $pricing = $pricingsByVehicleYearKey !== null
                ? ($pricingsByVehicleYearKey[$contract->vehicle_id.'.'.$monthStart->year] ?? null)
                : $this->pricingRepo->findForVehicleAndYear($contract->vehicle_id, $monthStart->year);

            if ($pricing === null) {
                return null;
            }

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $daysUsed,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );

            $total += $breakdown->totalCents;

            $cursor = $cursor->addMonth();
        }

        return $total;
    }

    /**
     * Batch rent for a list of preloaded contracts · 1 SQL for all
     * pricings (vehicle × year) then in-memory aggregation. Avoids N+1
     * on paginated indexes (typically 25 contracts per page).
     *
     * @param  iterable<Contract>  $contracts
     * @return array<int, ?int> contractId → cents (null when pricing missing)
     */
    public function forContracts(iterable $contracts): array
    {
        $vehicleYearKeys = [];
        $contractsList = [];

        foreach ($contracts as $contract) {
            $contractsList[] = $contract;
            $start = $contract->start_date->toImmutable();
            $end = $contract->end_date->toImmutable();

            $cursor = $start->startOfMonth();
            while (! $cursor->isAfter($end)) {
                $vehicleYearKeys[$contract->vehicle_id][$cursor->year] = true;
                $cursor = $cursor->addMonth();
            }
        }

        if ($contractsList === []) {
            return [];
        }

        // Group lookups by year (1 SQL per traversed year; typically 1,
        // occasionally 2 for cross-year contracts).
        $pricingsByKey = [];
        $yearToVehicleIds = [];
        foreach ($vehicleYearKeys as $vehicleId => $yearsSet) {
            foreach (array_keys($yearsSet) as $year) {
                $yearToVehicleIds[$year][] = $vehicleId;
            }
        }
        foreach ($yearToVehicleIds as $year => $vehicleIds) {
            $pricings = $this->pricingRepo->findForVehiclesAndYear(array_values(array_unique($vehicleIds)), (int) $year);
            foreach ($pricings as $vehicleId => $pricing) {
                $pricingsByKey[$vehicleId.'.'.$year] = $pricing;
            }
        }

        $results = [];
        foreach ($contractsList as $contract) {
            $results[$contract->id] = $this->forContractModel($contract, $pricingsByKey);
        }

        return $results;
    }

    /**
     * Yearly rent for a single vehicle (cross-companies sum of the 12
     * monthly billings). Null if the yearly pricing is missing.
     * Delegates to {@see forVehiclesAndYear()} so single calls stay
     * efficient (2 SQL instead of 12).
     */
    public function forVehicleAndYear(int $vehicleId, int $year): ?int
    {
        $results = $this->forVehiclesAndYear([$vehicleId], $year);

        return $results[$vehicleId] ?? null;
    }

    /**
     * Yearly rent for a company (sum of its 12 monthly invoices). Null
     * if any vehicle lacks a pricing for the year · the user must fix
     * the data before the total can be read. Delegates to
     * {@see forCompaniesAndYear()} so single calls stay efficient (2 SQL).
     */
    public function forCompanyAndYear(int $companyId, int $year): ?int
    {
        $results = $this->forCompaniesAndYear([$companyId], $year);

        return $results[$companyId] ?? null;
    }

    /**
     * Batched variant of {@see forCompanyAndYear()} · yearly rent for
     * several companies in 2 SQL (1 contracts-in-year for all companies
     * + 1 batch pricings) then in-memory aggregation per company.
     * Collapses the per-company N+1 of the company index rental column.
     *
     * @param  list<int>  $companyIds
     * @return array<int, ?int> companyId → cents (null when a vehicle lacks pricing)
     */
    public function forCompaniesAndYear(array $companyIds, int $year): array
    {
        if ($companyIds === []) {
            return [];
        }

        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = $yearStart->endOfYear();

        $contracts = $this->contractRepo->findForCompaniesInPeriod(
            $companyIds,
            $yearStart->toDateString(),
            $yearEnd->toDateString(),
        );

        $contractsByCompany = [];
        $vehicleIds = [];
        foreach ($contracts as $contract) {
            $contractsByCompany[$contract->company_id][] = $contract;
            $vehicleIds[$contract->vehicle_id] = true;
        }

        $pricings = $this->pricingRepo->findForVehiclesAndYear(array_keys($vehicleIds), $year);

        $results = [];
        foreach ($companyIds as $companyId) {
            $results[$companyId] = $this->companyYearTotal(
                $contractsByCompany[$companyId] ?? [],
                $pricings,
                $yearStart,
                $yearEnd,
            );
        }

        return $results;
    }

    /**
     * Cross-year variant of {@see forCompanyAndYear()} for one company ·
     * yearly rent for several years in 2 SQL (1 contracts over the full
     * range + 1 batched pricings over every involved vehicle × year),
     * then in-memory aggregation per year. Collapses the per-year N+1 of
     * the company fiche history (one forCompanyAndYear per active year).
     *
     * @param  list<int>  $years
     * @return array<int, ?int> year → cents (null when a vehicle lacks pricing that year)
     */
    public function forCompanyAndYears(int $companyId, array $years): array
    {
        if ($years === []) {
            return [];
        }

        $rangeStart = CarbonImmutable::create(min($years), 1, 1);
        $rangeEnd = CarbonImmutable::create(max($years), 12, 31);

        $contracts = $this->contractRepo->findForCompaniesInPeriod(
            [$companyId],
            $rangeStart->toDateString(),
            $rangeEnd->toDateString(),
        );

        $vehicleIds = [];
        foreach ($contracts as $contract) {
            $vehicleIds[$contract->vehicle_id] = true;
        }
        $pricingsByYear = $vehicleIds === []
            ? []
            : $this->pricingRepo->findForVehiclesAndYears(array_keys($vehicleIds), $years);

        $results = [];
        foreach ($years as $year) {
            $yearStart = CarbonImmutable::create($year, 1, 1);
            $yearEnd = $yearStart->endOfYear();

            $yearContracts = [];
            foreach ($contracts as $contract) {
                if ($contract->start_date->toDateString() > $yearEnd->toDateString()) {
                    continue;
                }
                if ($contract->end_date->toDateString() < $yearStart->toDateString()) {
                    continue;
                }
                $yearContracts[] = $contract;
            }

            $results[$year] = $this->companyYearTotal(
                $yearContracts,
                $pricingsByYear[$year] ?? [],
                $yearStart,
                $yearEnd,
            );
        }

        return $results;
    }

    /**
     * Sum of the 12 monthly billings for one company over the year, from
     * already-loaded contracts + pricings. Shared core between
     * {@see forCompanyAndYear()} and {@see forCompaniesAndYear()} to
     * guarantee strict equivalence. Returns `null` as soon as a used
     * vehicle lacks a pricing for the year, `0` when there is no contract.
     *
     * @param  list<Contract>  $contracts
     * @param  array<int, VehicleYearlyPricing>  $pricings  vehicleId → pricing
     */
    private function companyYearTotal(
        array $contracts,
        array $pricings,
        CarbonImmutable $yearStart,
        CarbonImmutable $yearEnd,
    ): ?int {
        // Expand by `(month × vehicle)`, dedupe days.
        $datesByMonthAndVehicle = [];
        $vehicleIds = [];

        foreach ($contracts as $contract) {
            $clipStart = $contract->start_date->isAfter($yearStart)
                ? $contract->start_date->toImmutable()
                : $yearStart;
            $clipEnd = $contract->end_date->isBefore($yearEnd)
                ? $contract->end_date->toImmutable()
                : $yearEnd;

            // exit_date clipping (defense in depth, ADR-0018).
            $exitDate = $contract->vehicle?->exit_date;
            if ($exitDate !== null) {
                $exitImmutable = $exitDate->toImmutable();
                if ($exitImmutable->isBefore($clipStart)) {
                    continue;
                }
                if ($exitImmutable->isBefore($clipEnd)) {
                    $clipEnd = $exitImmutable;
                }
            }

            if ($clipStart->isAfter($clipEnd)) {
                continue;
            }

            $vehicleIds[$contract->vehicle_id] = true;

            $cursor = $clipStart;
            while (! $cursor->isAfter($clipEnd)) {
                $monthKey = $cursor->month;
                $datesByMonthAndVehicle[$monthKey][$contract->vehicle_id][$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        }

        foreach (array_keys($vehicleIds) as $vehicleId) {
            if (! isset($pricings[$vehicleId])) {
                return null;
            }
        }

        $total = 0;
        foreach ($datesByMonthAndVehicle as $byVehicle) {
            foreach ($byVehicle as $vehicleId => $datesSet) {
                $pricing = $pricings[$vehicleId];
                $breakdown = OptimalRateBreakdown::compute(
                    daysUsed: count($datesSet),
                    dailyCents: $pricing->daily_rate_cents,
                    weeklyCents: $pricing->weekly_rate_cents,
                    monthlyCents: $pricing->monthly_rate_cents,
                );
                $total += $breakdown->totalCents;
            }
        }

        return $total;
    }

    /**
     * Batched variant of {@see forVehicleAndYear()} for indexes · 2 SQL
     * total then in-memory aggregation. Sequence semantics align with
     * {@see BillingCalculator::calculateForVehicleAndMonth()}.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, ?int> vehicleId → cents (null when pricing missing)
     */
    public function forVehiclesAndYear(array $vehicleIds, int $year): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $pricings = $this->pricingRepo->findForVehiclesAndYear($vehicleIds, $year);
        $contracts = $this->contractRepo->findForVehiclesInYear($vehicleIds, $year);

        $contractsByVehicle = [];
        foreach ($contracts as $contract) {
            $contractsByVehicle[$contract->vehicle_id][] = $contract;
        }

        $results = [];
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        foreach ($vehicleIds as $vehicleId) {
            if (! isset($pricings[$vehicleId])) {
                $results[$vehicleId] = null;

                continue;
            }

            $pricing = $pricings[$vehicleId];
            $vehicleContracts = $contractsByVehicle[$vehicleId] ?? [];

            $datesByMonthAndCompany = [];

            foreach ($vehicleContracts as $contract) {
                $clipStart = $contract->start_date->toImmutable();
                $clipEnd = $contract->end_date->toImmutable();

                if ($clipStart->isBefore($yearStart)) {
                    $clipStart = $yearStart;
                }
                if ($clipEnd->isAfter($yearEnd)) {
                    $clipEnd = $yearEnd;
                }

                $exitDate = $contract->vehicle?->exit_date;
                if ($exitDate !== null) {
                    $exitImmutable = $exitDate->toImmutable();
                    if ($exitImmutable->isBefore($clipStart)) {
                        continue;
                    }
                    if ($exitImmutable->isBefore($clipEnd)) {
                        $clipEnd = $exitImmutable;
                    }
                }

                $cursor = $clipStart;
                while (! $cursor->isAfter($clipEnd)) {
                    $monthKey = $cursor->format('Y-m');
                    $datesByMonthAndCompany[$monthKey][$contract->company_id][$cursor->toDateString()] = true;
                    $cursor = $cursor->addDay();
                }
            }

            $total = 0;
            foreach ($datesByMonthAndCompany as $byCompany) {
                foreach ($byCompany as $datesSet) {
                    $days = count($datesSet);
                    $breakdown = OptimalRateBreakdown::compute(
                        daysUsed: $days,
                        dailyCents: $pricing->daily_rate_cents,
                        weeklyCents: $pricing->weekly_rate_cents,
                        monthlyCents: $pricing->monthly_rate_cents,
                    );
                    $total += $breakdown->totalCents;
                }
            }

            $results[$vehicleId] = $total;
        }

        return $results;
    }
}
