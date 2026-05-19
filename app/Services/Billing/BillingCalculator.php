<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Billing\BillingCalculationData;
use App\Data\User\Billing\BillingLineData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Carbon\CarbonImmutable;

/**
 * Core of the V1.2 billing module · computes, for a triplet
 * `(company × year × civil month)`, the detailed monthly invoice
 * per vehicle.
 *
 * Pipeline ·
 *   1. Civil month bounds `[1st, last-of-month]`.
 *   2. Contracts overlapping that window for the company
 *      (eager-loaded `vehicle`).
 *   3. For every unique vehicle · expand the used dates (deduped
 *      across successive contracts within the month).
 *   4. Exhaustive yearly-pricing check · every vehicle without a
 *      pricing is collected and the exception is raised AFTER the
 *      full scan (UX · see every missing pricing at once instead of
 *      one at a time).
 *   5. Apply `OptimalRateBreakdown` and compose `BillingLineData`
 *      ordered by license plate.
 *
 * ADR-0013 compliant · pure application service, no SQL here.
 */
final readonly class BillingCalculator
{
    public function __construct(
        private ContractReadRepositoryInterface $contractRepository,
        private VehicleYearlyPricingReadRepositoryInterface $pricingRepository,
    ) {}

    /**
     * @throws MissingPricingException when at least one vehicle on the
     *                                 period has no pricing for the year.
     * @throws \InvalidArgumentException when the month is outside [1, 12].
     */
    public function calculate(int $companyId, int $year, int $month): BillingCalculationData
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Month must be in [1, 12], got {$month}.");
        }

        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth();

        $contracts = $this->contractRepository->findForCompanyInPeriod(
            $companyId,
            $monthStart->toDateString(),
            $monthEnd->toDateString(),
        );

        if ($contracts->isEmpty()) {
            return new BillingCalculationData(
                companyId: $companyId,
                year: $year,
                month: $month,
                lines: [],
                totalCents: 0,
            );
        }

        // Per-vehicle used dates (kept as a list so the
        // `DiscountApplier` can compute pro-rata day by day). `daysUsed`
        // is `count($usedDates)`.
        $usedDatesByVehicle = $this->aggregateUsedDatesByVehicle($contracts, $monthStart, $monthEnd);
        $daysByVehicle = array_map(static fn (array $dates): int => count($dates), $usedDatesByVehicle);

        // Single batch lookup for every vehicle of the month · removes
        // the N+1 when looping `byCompanyForYear` over 12 months.
        $vehicles = $this->indexVehiclesById($contracts);
        $vehicleIds = array_keys($daysByVehicle);
        $pricings = $this->pricingRepository->findForVehiclesAndYear($vehicleIds, $year);

        $missing = [];
        foreach ($vehicleIds as $vehicleId) {
            if (! isset($pricings[$vehicleId])) {
                $missing[] = [
                    'vehicleId' => $vehicleId,
                    'licensePlate' => $vehicles[$vehicleId]->license_plate,
                    'year' => $year,
                ];
            }
        }

        if ($missing !== []) {
            throw MissingPricingException::forMissingItems($missing);
        }

        $lines = [];
        foreach ($daysByVehicle as $vehicleId => $daysUsed) {
            $vehicle = $vehicles[$vehicleId];
            $pricing = $pricings[$vehicleId];

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $daysUsed,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );

            // `grossTotalCents` is initialised to the breakdown total
            // (= net while no discount applies; the `DiscountApplier`
            // overrides this downstream).
            $lines[] = new BillingLineData(
                vehicleId: $vehicleId,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $daysUsed,
                monthsBilled: $breakdown->months,
                weeksBilled: $breakdown->weeks,
                daysBilled: $breakdown->days,
                dailyRateCents: $pricing->daily_rate_cents,
                weeklyRateCents: $pricing->weekly_rate_cents,
                monthlyRateCents: $pricing->monthly_rate_cents,
                totalCents: $breakdown->totalCents,
                grossTotalCents: $breakdown->totalCents,
                discountCents: 0,
                appliedDiscountId: null,
                appliedDiscountBasisPoints: null,
                appliedDiscountLabel: null,
                usedDates: $usedDatesByVehicle[$vehicleId] ?? [],
            );
        }

        usort(
            $lines,
            static fn (BillingLineData $a, BillingLineData $b): int => strcmp($a->licensePlate, $b->licensePlate),
        );

        $total = array_sum(array_map(static fn (BillingLineData $l): int => $l->totalCents, $lines));
        $gross = array_sum(array_map(static fn (BillingLineData $l): int => $l->grossTotalCents, $lines));
        $discount = array_sum(array_map(static fn (BillingLineData $l): int => $l->discountCents, $lines));

        return new BillingCalculationData(
            companyId: $companyId,
            year: $year,
            month: $month,
            lines: $lines,
            totalCents: $total,
            grossTotalCents: $gross,
            totalDiscountCents: $discount,
        );
    }

    /**
     * Computes the twelve monthly invoices for a company over a year
     * · 1 SQL for the contracts of the year + 1 SQL batched for the
     * pricings of the involved vehicles, then in-memory month-by-month
     * iteration. Strictly equivalent to 12 successive `calculate()`
     * calls but without the 12 round-trips.
     *
     * @return array<int, BillingCalculationData|MissingPricingException>
     *                                                                    Key · month [1..12]. A `MissingPricingException` replaces the
     *                                                                    `BillingCalculationData` for a month that would have thrown.
     */
    public function calculateYear(int $companyId, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = $yearStart->endOfYear();

        $allContracts = $this->contractRepository->findForCompanyInPeriod(
            $companyId,
            $yearStart->toDateString(),
            $yearEnd->toDateString(),
        );

        // Pre-batch the pricings of every vehicle of the year (1 SQL).
        $allVehicleIds = [];
        foreach ($allContracts as $contract) {
            $allVehicleIds[$contract->vehicle_id] = true;
        }
        $pricingsAll = $allVehicleIds === []
            ? []
            : $this->pricingRepository->findForVehiclesAndYear(array_keys($allVehicleIds), $year);

        $vehiclesAll = $this->indexVehiclesById($allContracts);

        $results = [];
        for ($month = 1; $month <= 12; $month++) {
            $results[$month] = $this->calculateMonthFromPreloaded(
                $companyId,
                $year,
                $month,
                $allContracts,
                $pricingsAll,
                $vehiclesAll,
            );
        }

        return $results;
    }

    /**
     * Full-batch variant of `calculateYear()` · takes the contracts
     * already filtered on the company (with `vehicle` eager-loaded for
     * `exit_date`) and the year pricings. Zero SQL · pure in-memory
     * iteration over the 12 months. Strictly equivalent to
     * `calculateYear($companyId, $year)` without the two round-trips ·
     * powers the Dashboard batch
     * {@see App\Services\Billing\BillingBreakdownService::totalRecettesForYears}
     * which loads every contract and pricing upstream (1 + 1 SQL for
     * N companies × M years).
     *
     * @param  iterable<int, Contract>  $companyYearContracts  Contracts already filtered on `(company, year)`, vehicle eager-loaded
     * @param  array<int, VehicleYearlyPricing>  $pricingsForYear  vehicleId → pricing for this year
     * @return array<int, BillingCalculationData|MissingPricingException> Key · month [1..12]
     */
    public function calculateYearWithPreloaded(
        int $companyId,
        int $year,
        iterable $companyYearContracts,
        array $pricingsForYear,
    ): array {
        $vehiclesAll = $this->indexVehiclesById($companyYearContracts);

        $results = [];
        for ($month = 1; $month <= 12; $month++) {
            $results[$month] = $this->calculateMonthFromPreloaded(
                $companyId,
                $year,
                $month,
                $companyYearContracts,
                $pricingsForYear,
                $vehiclesAll,
            );
        }

        return $results;
    }

    /**
     * In-memory variant of `calculate()` taking preloaded contracts
     * and pricings · used by the batched `calculateYear`.
     *
     * @param  iterable<int, Contract>  $allContracts  Year-scoped contracts (already filtered on company)
     * @param  array<int, mixed>  $pricingsAll  vehicleId → VehicleYearlyPricing
     * @param  array<int, Vehicle>  $vehiclesAll  vehicleId → Vehicle
     */
    private function calculateMonthFromPreloaded(
        int $companyId,
        int $year,
        int $month,
        iterable $allContracts,
        array $pricingsAll,
        array $vehiclesAll,
    ): BillingCalculationData|MissingPricingException {
        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth();

        $monthContracts = [];
        foreach ($allContracts as $contract) {
            if ($contract->start_date->toDateString() > $monthEnd->toDateString()) {
                continue;
            }
            if ($contract->end_date->toDateString() < $monthStart->toDateString()) {
                continue;
            }
            $monthContracts[] = $contract;
        }

        if ($monthContracts === []) {
            return new BillingCalculationData(
                companyId: $companyId,
                year: $year,
                month: $month,
                lines: [],
                totalCents: 0,
            );
        }

        $usedDatesByVehicle = $this->aggregateUsedDatesByVehicle($monthContracts, $monthStart, $monthEnd);
        $daysByVehicle = array_map(static fn (array $dates): int => count($dates), $usedDatesByVehicle);
        $vehicleIds = array_keys($daysByVehicle);

        $missing = [];
        foreach ($vehicleIds as $vehicleId) {
            if (! isset($pricingsAll[$vehicleId])) {
                $missing[] = [
                    'vehicleId' => $vehicleId,
                    'licensePlate' => $vehiclesAll[$vehicleId]->license_plate ?? "#{$vehicleId}",
                    'year' => $year,
                ];
            }
        }

        if ($missing !== []) {
            return MissingPricingException::forMissingItems($missing);
        }

        $lines = [];
        foreach ($daysByVehicle as $vehicleId => $daysUsed) {
            $vehicle = $vehiclesAll[$vehicleId];
            $pricing = $pricingsAll[$vehicleId];

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $daysUsed,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );

            $lines[] = new BillingLineData(
                vehicleId: $vehicleId,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $daysUsed,
                monthsBilled: $breakdown->months,
                weeksBilled: $breakdown->weeks,
                daysBilled: $breakdown->days,
                dailyRateCents: $pricing->daily_rate_cents,
                weeklyRateCents: $pricing->weekly_rate_cents,
                monthlyRateCents: $pricing->monthly_rate_cents,
                totalCents: $breakdown->totalCents,
                grossTotalCents: $breakdown->totalCents,
                discountCents: 0,
                appliedDiscountId: null,
                appliedDiscountBasisPoints: null,
                appliedDiscountLabel: null,
                usedDates: $usedDatesByVehicle[$vehicleId] ?? [],
            );
        }

        usort(
            $lines,
            static fn (BillingLineData $a, BillingLineData $b): int => strcmp($a->licensePlate, $b->licensePlate),
        );

        $total = array_sum(array_map(static fn (BillingLineData $l): int => $l->totalCents, $lines));
        $gross = array_sum(array_map(static fn (BillingLineData $l): int => $l->grossTotalCents, $lines));
        $discount = array_sum(array_map(static fn (BillingLineData $l): int => $l->discountCents, $lines));

        return new BillingCalculationData(
            companyId: $companyId,
            year: $year,
            month: $month,
            lines: $lines,
            totalCents: $total,
            grossTotalCents: $gross,
            totalDiscountCents: $discount,
        );
    }

    /**
     * Cross-company revenue total for one vehicle in one civil month
     * · sum of the `(vehicle × company × month)` couple invoices. A
     * vehicle rented to two distinct companies the same month produces
     * two independent invoices · each with its own optimal
     * day/week/month combo · and the vehicle's monthly revenue is the
     * sum of both. You CANNOT sum the days cross-company and apply
     * `OptimalRateBreakdown` once · that would be semantically wrong
     * because each invoice is emitted separately (10 d × 2 =
     * 154 000 c, ≠ 20 d in one shot = 150 000 c with realistic
     * 90/500/1800 rates).
     *
     * Also returns `daysUsed` (cross-company sum, intra-company
     * deduped) for the vehicle recap.
     *
     * @return array{daysUsed: int, totalCents: int}
     *
     * @throws MissingPricingException when the vehicle has no pricing
     *                                 for the year.
     * @throws \InvalidArgumentException when the month is outside [1, 12].
     */
    public function calculateForVehicleAndMonth(int $vehicleId, int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Month must be in [1, 12], got {$month}.");
        }

        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth();

        $contracts = $this->contractRepository->findWindowContractsForVehicle(
            $vehicleId,
            $monthStart,
            $monthEnd,
        );

        if ($contracts->isEmpty()) {
            return ['daysUsed' => 0, 'totalCents' => 0];
        }

        $pricing = $this->pricingRepository->findForVehicleAndYear($vehicleId, $year);

        if ($pricing === null) {
            // Need a plate for the UX message. Every contract here
            // carries the same vehicle, so any of them works
            // (`findWindowContractsForVehicle` does not guarantee the
            // eager-load).
            $first = $contracts->first();
            $licensePlate = $first?->vehicle->license_plate ?? "#{$vehicleId}";

            throw MissingPricingException::forMissingItems([
                ['vehicleId' => $vehicleId, 'licensePlate' => $licensePlate, 'year' => $year],
            ]);
        }

        // Group by company, dedup days, apply OptimalRateBreakdown per
        // (vehicle × company × month) couple, sum the totals.
        $datesByCompany = $this->expandContractsByKey(
            $contracts,
            $monthStart,
            $monthEnd,
            static fn (Contract $contract): int => $contract->company_id,
        );

        $daysUsed = 0;
        $totalCents = 0;

        foreach ($datesByCompany as $datesSet) {
            $perCompanyDays = count($datesSet);
            $daysUsed += $perCompanyDays;

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $perCompanyDays,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );
            $totalCents += $breakdown->totalCents;
        }

        return ['daysUsed' => $daysUsed, 'totalCents' => $totalCents];
    }

    /**
     * Aggregates per-vehicle used dates over the `[start, end]`
     * window, deduping dates shared by multiple contracts. Vehicle
     * appearance order is preserved (sorting by plate happens
     * downstream). Returns the full list of ISO Y-m-d dates so the
     * `DiscountApplier` downstream can compute partial pro-ratas day
     * by day.
     *
     * @param  iterable<int, Contract>  $contracts
     * @return array<int, list<string>> vehicleId → sorted list of ISO Y-m-d dates
     */
    private function aggregateUsedDatesByVehicle(
        iterable $contracts,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
    ): array {
        $datesByVehicle = $this->expandContractsByKey(
            $contracts,
            $monthStart,
            $monthEnd,
            static fn (Contract $contract): int => $contract->vehicle_id,
        );

        // Convert `array<dateStr, true>` to `list<dateStr>` · the
        // chronological order is preserved by the ordered insertion in
        // `expandContractsByKey`.
        return array_map(
            static fn (array $set): array => array_keys($set),
            $datesByVehicle,
        );
    }

    /**
     * Expansion of a contracts collection into
     * `array<key, set-of-dates>` over `[monthStart, monthEnd]`, with
     * `exit_date` clipping (ADR-0018) and intra-key dedup.
     *
     * Shared helper between {@see aggregateUsedDatesByVehicle} (key
     * `vehicle_id`, feeds the company-side `calculate()`) and
     * {@see calculateForVehicleAndMonth} (key `company_id`, feeds the
     * cross-company vehicle recap).
     *
     * `exit_date` clipping (defense in depth) · the `AvailableForPeriod`
     * validation rule should already block any contract overflowing
     * `exit_date`, but we still guard against residual inconsistencies
     * (post-creation `exit_date` change, inherited data, …).
     *
     * @template TKey of int|string
     *
     * @param  iterable<int, Contract>  $contracts
     * @param  callable(Contract): TKey  $keyOf
     * @return array<TKey, array<string, true>>
     */
    private function expandContractsByKey(
        iterable $contracts,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        callable $keyOf,
    ): array {
        /** @var array<TKey, array<string, true>> $byKey */
        $byKey = [];

        foreach ($contracts as $contract) {
            $clipStart = $contract->start_date->isAfter($monthStart)
                ? $contract->start_date->toImmutable()
                : $monthStart;
            $clipEnd = $contract->end_date->isBefore($monthEnd)
                ? $contract->end_date->toImmutable()
                : $monthEnd;

            $exitDate = $contract->vehicle?->exit_date;
            if ($exitDate !== null) {
                $exitDateImmutable = $exitDate->toImmutable();
                if ($exitDateImmutable->isBefore($clipStart)) {
                    continue;
                }
                if ($exitDateImmutable->isBefore($clipEnd)) {
                    $clipEnd = $exitDateImmutable;
                }
            }

            if ($clipStart->isAfter($clipEnd)) {
                continue;
            }

            $key = $keyOf($contract);
            $cursor = $clipStart;
            while (! $cursor->isAfter($clipEnd)) {
                $byKey[$key][$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        }

        return $byKey;
    }

    /**
     * Indexes vehicles by id from eager-loaded contracts · assumes
     * `vehicle` is loaded on each contract.
     *
     * @param  iterable<int, Contract>  $contracts
     * @return array<int, Vehicle>
     */
    private function indexVehiclesById(iterable $contracts): array
    {
        $byId = [];
        foreach ($contracts as $contract) {
            /** @var Vehicle $vehicle */
            $vehicle = $contract->vehicle;
            $byId[$vehicle->id] = $vehicle;
        }

        return $byId;
    }
}
