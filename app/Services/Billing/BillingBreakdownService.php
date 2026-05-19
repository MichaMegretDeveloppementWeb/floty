<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Billing\ContractBillingBreakdownData;
use App\Data\User\Billing\ContractBillingMonthData;
use App\Data\User\Billing\MonthlyBillingBreakdownData;
use App\Data\User\Billing\MonthlyBreakdownEntryData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Contract;
use App\Services\Billing\Discount\DiscountApplier;
use App\Services\Billing\Discount\DiscountResolver;
use App\Services\Billing\Discount\ResolvedDiscountIndex;

/**
 * Composes the 12-month billing recaps consumed by the Vehicle and
 * Company Show fiches.
 *
 * Doctrine · on 12 months, billing computations accumulate via
 * {@see BillingCalculator}. When a month throws
 * `MissingPricingException`, the loop is not interrupted · that month
 * is flagged `hasMissingPricing = true` and the recap stays usable
 * even if the user has only set part of the rates.
 */
final class BillingBreakdownService
{
    /**
     * Per-instance cache of 12-month recaps per company, indexed by
     * `"{companyId}|{year}"`. Hot path · the Dashboard iterates
     * 8 years × 15 companies = 120 calls per mount; most touch the
     * same `(companyId, year)` couples across consumer services.
     *
     * Safety · the DTO is deterministic on `(companyId, year)` from
     * read-only DB state. No mutation between two calls in the same
     * HTTP request. Singleton scoped per-request.
     *
     * @var array<string, MonthlyBillingBreakdownData>
     */
    private array $byCompanyForYearCache = [];

    /** @var array<string, MonthlyBillingBreakdownData> */
    private array $byVehicleForYearCache = [];

    /**
     * Per-instance cache of cross-company yearly totals computed via
     * {@see totalRecettesForYears()} · lets Dashboard computations
     * share results within a defer wave.
     *
     * @var array<int, int>
     */
    private array $totalRecettesForYearsCache = [];

    public function __construct(
        private readonly BillingCalculator $calculator,
        private readonly VehicleYearlyPricingReadRepositoryInterface $pricingRepository,
        private readonly InvoiceReadRepositoryInterface $invoiceRepository,
        private readonly ContractReadRepositoryInterface $contractRepository,
        private readonly VehicleReadRepositoryInterface $vehicleRepository,
        private readonly DiscountResolver $discountResolver,
        private readonly DiscountApplier $discountApplier,
    ) {}

    /**
     * 12-month recap for the Company fiche · cross-vehicle aggregate
     * (used vehicles × billed rates). A month is flagged "missing
     * pricing" as soon as a single vehicle present that month lacks
     * a yearly pricing.
     */
    public function byCompanyForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        $key = $companyId.'|'.$year;

        return $this->byCompanyForYearCache[$key] ??= $this->computeByCompanyForYear($companyId, $year);
    }

    private function computeByCompanyForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        $entries = [];
        $totalDays = 0;
        $totalCents = 0;
        $totalGrossCents = 0;
        $totalDiscountCents = 0;
        $hasAnyMissing = false;

        // Single lookup for already-emitted invoices on the
        // `(company × year)` pair, indexed by month · powers the
        // « Voir #YYYY-MM-NNNN » CTA without paying 12 lookups.
        $existingInvoices = $this->invoiceRepository->findExistingByMonthForCompanyYear($companyId, $year);

        // Batch all 12 months in 2 SQL queries (year contracts +
        // batched pricings) instead of 12× findForCompanyInPeriod + N
        // pricings.
        $monthlyResults = $this->calculator->calculateYear($companyId, $year);

        // Apply commercial discounts in post-processing. Strict
        // equivalence branch · when no active discount, the inputs
        // are returned unchanged.
        $discountIndex = $this->discountResolver->preloadForCompanyYear($companyId, $year);
        $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $discountIndex);

        for ($month = 1; $month <= 12; $month++) {
            $existing = $existingInvoices[$month] ?? null;
            $result = $monthlyResults[$month];

            if ($result instanceof MissingPricingException) {
                // Blocked month · `daysUsed` stays at 0 to avoid
                // misleading totals. The flag drives the UI tooltip
                // « renseignez le tarif <X> sur la fiche véhicule ».
                $entries[] = new MonthlyBreakdownEntryData(
                    month: $month,
                    daysUsed: 0,
                    totalCents: null,
                    hasMissingPricing: true,
                    existingInvoiceId: $existing['id'] ?? null,
                    existingInvoiceNumber: $existing['invoiceNumber'] ?? null,
                    invoicedDaysUsed: $existing['invoicedDaysUsed'] ?? null,
                    invoicedTotalCents: $existing['totalHtCents'] ?? null,
                    grossTotalCents: null,
                    totalDiscountCents: null,
                    invoicedGrossTotalCents: $existing['grossTotalCents'] ?? null,
                    invoicedTotalDiscountCents: $existing['totalDiscountCents'] ?? null,
                );
                $hasAnyMissing = true;

                continue;
            }

            $monthDays = array_sum(array_map(
                static fn ($l) => $l->daysUsed,
                $result->lines,
            ));
            $entries[] = new MonthlyBreakdownEntryData(
                month: $month,
                daysUsed: $monthDays,
                totalCents: $result->totalCents,
                hasMissingPricing: false,
                existingInvoiceId: $existing['id'] ?? null,
                existingInvoiceNumber: $existing['invoiceNumber'] ?? null,
                invoicedDaysUsed: $existing['invoicedDaysUsed'] ?? null,
                invoicedTotalCents: $existing['totalHtCents'] ?? null,
                grossTotalCents: $result->grossTotalCents,
                totalDiscountCents: $result->totalDiscountCents,
                invoicedGrossTotalCents: $existing['grossTotalCents'] ?? null,
                invoicedTotalDiscountCents: $existing['totalDiscountCents'] ?? null,
            );
            $totalDays += $monthDays;
            $totalCents += $result->totalCents;
            $totalGrossCents += $result->grossTotalCents;
            $totalDiscountCents += $result->totalDiscountCents;
        }

        return new MonthlyBillingBreakdownData(
            year: $year,
            entries: $entries,
            yearTotalDaysUsed: $totalDays,
            yearTotalCents: $hasAnyMissing ? null : $totalCents,
            // Partial total (months without missing pricing) is
            // always populated.
            yearTotalCentsPartial: $totalCents,
            hasAnyMissingPricing: $hasAnyMissing,
            yearTotalGrossCentsPartial: $totalGrossCents,
            yearTotalDiscountCentsPartial: $totalDiscountCents,
        );
    }

    /**
     * Cross-company HT revenue totals across the Dashboard scope ·
     * three fixed SQL queries regardless of N companies × M years ·
     *
     *   1. Active contracts crossing `[min(years), max(years)]`.
     *   2. Vehicles by ids (with `exit_date` for ADR-0018 clipping).
     *   3. Pricings batched `(vehicleIds × years)`.
     *
     * Replaces the N × M calls to {@see byCompanyForYear()} (each
     * doing 3 queries · contracts + pricings + invoices), up to 120+
     * queries for a 15-company × 2-year Dashboard, when only the
     * `yearTotalCentsPartial` is needed.
     *
     * Strictly equivalent to
     * `Σ_companies byCompanyForYear(company, year)->yearTotalCentsPartial`.
     *
     * @param  list<int>  $years
     * @return array<int, int> year → totalCentsPartial cross-companies
     */
    public function totalRecettesForYears(array $years): array
    {
        if ($years === []) {
            return [];
        }

        $missingYears = array_values(array_filter(
            $years,
            fn (int $y): bool => ! array_key_exists($y, $this->totalRecettesForYearsCache),
        ));

        if ($missingYears !== []) {
            $computed = $this->computeTotalRecettesForYears($missingYears);
            foreach ($computed as $y => $total) {
                $this->totalRecettesForYearsCache[$y] = $total;
            }
        }

        $result = [];
        foreach ($years as $y) {
            $result[$y] = $this->totalRecettesForYearsCache[$y];
        }

        return $result;
    }

    /**
     * @param  list<int>  $years
     * @return array<int, int>
     */
    private function computeTotalRecettesForYears(array $years): array
    {
        $minYear = min($years);
        $maxYear = max($years);

        $contracts = $this->contractRepository->findActiveForYearRange($minYear, $maxYear);

        if ($contracts->isEmpty()) {
            return array_fill_keys($years, 0);
        }

        $vehicleIdsSet = [];
        foreach ($contracts as $contract) {
            $vehicleIdsSet[(int) $contract->vehicle_id] = true;
        }
        $vehicleIdList = array_keys($vehicleIdsSet);

        $vehiclesById = $this->vehicleRepository->findByIdsIndexed($vehicleIdList);

        // Hydrate `vehicle` on each contract in-memory · avoids N+1
        // inside `expandContractsByKey` which reads
        // `$contract->vehicle?->exit_date`.
        foreach ($contracts as $contract) {
            $contract->setRelation('vehicle', $vehiclesById->get($contract->vehicle_id));
        }

        $pricingsByYearByVehicle = $this->pricingRepository->findForVehiclesAndYears(
            $vehicleIdList,
            $years,
        );

        // Group contracts by `(companyId, year)` in memory (zero
        // query). A multi-year contract is dispatched into every
        // year it covers ∩ $years.
        $byCompanyByYear = [];
        foreach ($contracts as $contract) {
            $startYear = (int) $contract->start_date->year;
            $endYear = (int) $contract->end_date->year;
            foreach ($years as $year) {
                if ($year < $startYear || $year > $endYear) {
                    continue;
                }
                $byCompanyByYear[(int) $contract->company_id][$year][] = $contract;
            }
        }

        $companyIdList = array_keys($byCompanyByYear);
        $discountIndexByCompanyByYear = [];
        foreach ($years as $year) {
            $discountIndexByCompanyByYear[$year] = $this->discountResolver
                ->preloadForCompaniesYear($companyIdList, $year);
        }

        // Compute the totals in memory (zero SQL). The discount
        // applier is a no-op when the index is empty.
        $totals = array_fill_keys($years, 0);
        foreach ($byCompanyByYear as $companyId => $byYear) {
            foreach ($byYear as $year => $companyYearContracts) {
                $pricingsForYear = $pricingsByYearByVehicle[$year] ?? [];
                $monthlyResults = $this->calculator->calculateYearWithPreloaded(
                    $companyId,
                    $year,
                    $companyYearContracts,
                    $pricingsForYear,
                );
                $index = $discountIndexByCompanyByYear[$year][$companyId] ?? ResolvedDiscountIndex::empty();
                $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $index);
                for ($month = 1; $month <= 12; $month++) {
                    $result = $monthlyResults[$month];
                    if (! $result instanceof MissingPricingException) {
                        $totals[$year] += $result->totalCents;
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * 12-month recap for the Vehicle fiche · monthly cross-company
     * revenue aggregate. No mutualised tariff combo · each company is
     * billed separately
     * (cf. {@see BillingCalculator::calculateForVehicleAndMonth()}).
     */
    public function byVehicleForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
    {
        $key = $vehicleId.'|'.$year;

        return $this->byVehicleForYearCache[$key] ??= $this->computeByVehicleForYear($vehicleId, $year);
    }

    private function computeByVehicleForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
    {
        $entries = [];
        $totalDays = 0;
        $totalCents = 0;
        $hasAnyMissing = false;

        for ($month = 1; $month <= 12; $month++) {
            try {
                $result = $this->calculator->calculateForVehicleAndMonth($vehicleId, $year, $month);
                $entries[] = new MonthlyBreakdownEntryData(
                    month: $month,
                    daysUsed: $result['daysUsed'],
                    totalCents: $result['totalCents'],
                    hasMissingPricing: false,
                );
                $totalDays += $result['daysUsed'];
                $totalCents += $result['totalCents'];
            } catch (MissingPricingException) {
                $entries[] = new MonthlyBreakdownEntryData(
                    month: $month,
                    daysUsed: 0,
                    totalCents: null,
                    hasMissingPricing: true,
                );
                $hasAnyMissing = true;
            }
        }

        return new MonthlyBillingBreakdownData(
            year: $year,
            entries: $entries,
            yearTotalDaysUsed: $totalDays,
            yearTotalCents: $hasAnyMissing ? null : $totalCents,
            yearTotalCentsPartial: $totalCents,
            hasAnyMissingPricing: $hasAnyMissing,
        );
    }

    /**
     * Per-contract isolated billing recap · for every civil month the
     * contract crosses, compute the isolated cost (contract days ×
     * vehicle pricing for the year).
     *
     * Caveat · if the vehicle has several contracts for the same
     * company over the same month, the isolated cost can differ from
     * the actual monthly invoice (which consolidates via
     * OptimalRateBreakdown on the unioned days). Accepted approximation
     * for the contract fiche · the invoice remains the source of truth.
     */
    public function byContract(Contract $contract): ContractBillingBreakdownData
    {
        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        $months = [];
        $totalDays = 0;
        $totalCents = 0;
        $totalGrossCents = 0;
        $totalDiscountCents = 0;
        $hasAnyMissing = false;

        // Local cache · do not preload twice for a single-year
        // contract.
        $discountIndexByYear = [];
        $loadIndex = function (int $year) use ($contract, &$discountIndexByYear): ResolvedDiscountIndex {
            return $discountIndexByYear[$year] ??= $this->discountResolver
                ->preloadForCompanyYear((int) $contract->company_id, $year);
        };

        $cursor = $start->startOfMonth();
        $endMonth = $end->startOfMonth();

        while (! $cursor->isAfter($endMonth)) {
            $monthStart = $cursor;
            $monthEnd = $cursor->endOfMonth();

            $clipStart = $start->isAfter($monthStart) ? $start : $monthStart;
            $clipEnd = $end->isBefore($monthEnd) ? $end : $monthEnd;

            $daysInMonth = (int) $clipStart->diffInDays($clipEnd) + 1;

            $year = $cursor->year;
            $pricing = $this->pricingRepository->findForVehicleAndYear($contract->vehicle_id, $year);

            if ($pricing === null) {
                $months[] = new ContractBillingMonthData(
                    year: $year,
                    month: $cursor->month,
                    daysInMonth: $daysInMonth,
                    totalCents: null,
                    hasMissingPricing: true,
                    grossTotalCents: null,
                    discountCents: null,
                );
                $totalDays += $daysInMonth;
                $hasAnyMissing = true;
            } else {
                $breakdown = OptimalRateBreakdown::compute(
                    daysUsed: $daysInMonth,
                    dailyCents: $pricing->daily_rate_cents,
                    weeklyCents: $pricing->weekly_rate_cents,
                    monthlyCents: $pricing->monthly_rate_cents,
                );

                // Inline discount application · pro-rata by days
                // covered by an active discount. Strict equivalence
                // when the index is empty (dominant case).
                $grossCents = $breakdown->totalCents;
                $discountCents = 0;
                $index = $loadIndex($year);
                if (! $index->isEmpty()) {
                    $discountedDays = 0;
                    $bp = 0;
                    $cursor2 = $clipStart;
                    while (! $cursor2->isAfter($clipEnd)) {
                        $discount = $index->findFor(
                            (int) $contract->vehicle_id,
                            $cursor2->toDateString(),
                        );
                        if ($discount !== null) {
                            $discountedDays++;
                            // The ConflictService guarantees a single
                            // active discount per `(vehicle, date)`,
                            // so capture the first one for the
                            // pro-rata.
                            if ($bp === 0) {
                                $bp = $discount->discount_basis_points;
                            }
                        }
                        $cursor2 = $cursor2->addDay();
                    }

                    if ($discountedDays > 0 && $bp > 0) {
                        $discountCents = (int) round(
                            $grossCents * $discountedDays * $bp
                                / ($daysInMonth * 10000),
                        );
                        if ($discountCents > $grossCents) {
                            $discountCents = $grossCents;
                        }
                    }
                }
                $netCents = $grossCents - $discountCents;

                $months[] = new ContractBillingMonthData(
                    year: $year,
                    month: $cursor->month,
                    daysInMonth: $daysInMonth,
                    totalCents: $netCents,
                    hasMissingPricing: false,
                    grossTotalCents: $grossCents,
                    discountCents: $discountCents,
                );
                $totalDays += $daysInMonth;
                $totalCents += $netCents;
                $totalGrossCents += $grossCents;
                $totalDiscountCents += $discountCents;
            }

            $cursor = $cursor->addMonth();
        }

        return new ContractBillingBreakdownData(
            months: $months,
            totalDaysUsed: $totalDays,
            totalCents: $hasAnyMissing ? null : $totalCents,
            hasAnyMissingPricing: $hasAnyMissing,
            totalGrossCentsPartial: $totalGrossCents,
            totalDiscountCentsPartial: $totalDiscountCents,
        );
    }
}
