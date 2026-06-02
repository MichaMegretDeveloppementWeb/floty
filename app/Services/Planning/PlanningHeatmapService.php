<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Planning\PlanningHeatmapCompanyVehicleData;
use App\Data\User\Planning\PlanningHeatmapVehicleData;
use App\Data\User\Planning\PlanningHeatmapVehicleFullYearCostsData;
use App\Data\User\Planning\PlanningHeatmapVehicleRealCostsData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Models\Unavailability;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\Discount\DiscountApplier;
use App\Services\Billing\Discount\DiscountResolver;
use App\Services\Billing\Discount\ResolvedDiscountIndex;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Support\Date\IsoWeeks;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\DataCollection;

/**
 * Builds the vehicles × 52 weeks matrix backing the planning overview.
 *
 * Consumes contracts via `ContractQueryService`; per-vehicle
 * unavailabilities are forwarded to the fiscal engine so R-2024-008
 * acts on raw data.
 *
 * Slim + deferred costs · {@see buildHeatmap()} and
 * {@see buildHeatmapForCompany()} no longer compute fiscal costs at
 * mount (~630 ms cold on 64 vehicles). The three per-vehicle amounts
 * (`annualTaxDue`, `fullYearTax`, `dailyTaxRate`) ship via
 * {@see fullYearCostsForVehicles()} and {@see realCostsForVehicles()}
 * as `Inertia::defer` props.
 */
final class PlanningHeatmapService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly VehicleYearlyPricingReadRepositoryInterface $pricings,
        private readonly BillingCalculator $billingCalculator,
        private readonly DiscountResolver $discountResolver,
        private readonly DiscountApplier $discountApplier,
        private readonly ContractReadRepositoryInterface $contractReader,
    ) {}

    /**
     * @return array{vehicles: DataCollection<int, PlanningHeatmapVehicleData>, companies: DataCollection<int, CompanyOptionData>}
     */
    public function buildHeatmap(int $year, string $sortDirection = 'asc'): array
    {
        $weekDensity = $this->contracts->loadWeekDensity($year);

        $vehicles = $this->vehicles->findAllForHeatmap($year, $sortDirection);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
        $pricingsByVehicleId = $this->pricings->findForVehiclesAndYear($vehicleIds, $year);

        $weeksCount = IsoWeeks::CELLS_PER_YEAR;

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeks = [];
            for ($w = 1; $w <= $weeksCount; $w++) {
                $weeks[] = $weekDensity[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];
            $pricing = $pricingsByVehicleId[$vehicle->id] ?? null;

            $vehicleRows[] = new PlanningHeatmapVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeks: $weeks,
                daysTotal: array_sum($weeks),
                exitDate: $vehicle->exit_date?->toDateString(),
                weeksWithUnavailability: $this->collectWeeksWithUnavailability($vehicleUnavailabilities, $year),
                dailyRateCents: $pricing?->daily_rate_cents,
                weeklyRateCents: $pricing?->weekly_rate_cents,
                monthlyRateCents: $pricing?->monthly_rate_cents,
            );
        }

        $companyRows = $this->companies->findAllForHeatmap()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapVehicleData::collect($vehicleRows, DataCollection::class),
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }

    /**
     * Company-scoped variant of {@see buildHeatmap()} for the Per
     * Company view. The cell number reflects only days used by the
     * selected company; the colour still follows the global density
     * (overall availability signal).
     *
     * @return array{
     *     vehicles: DataCollection<int, PlanningHeatmapCompanyVehicleData>,
     *     company: CompanyOptionData,
     *     companies: DataCollection<int, CompanyOptionData>,
     * }
     */
    public function buildHeatmapForCompany(int $year, Company $company, string $sortDirection = 'asc'): array
    {
        $weekDensityGlobal = $this->contracts->loadWeekDensity($year);
        $weekDensityForCompany = $this->contracts->loadWeekDensityForCompany($year, $company->id);

        $vehicles = $this->vehicles->findAllForHeatmap($year, $sortDirection);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
        $pricingsByVehicleId = $this->pricings->findForVehiclesAndYear($vehicleIds, $year);

        $weeksCount = IsoWeeks::CELLS_PER_YEAR;

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeksGlobal = [];
            $weeksForCompany = [];
            for ($w = 1; $w <= $weeksCount; $w++) {
                $weeksGlobal[] = $weekDensityGlobal[$vehicle->id.'|'.$w] ?? 0;
                $weeksForCompany[] = $weekDensityForCompany[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];
            $pricing = $pricingsByVehicleId[$vehicle->id] ?? null;

            $vehicleRows[] = new PlanningHeatmapCompanyVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeksGlobal: $weeksGlobal,
                weeksForCompany: $weeksForCompany,
                daysTotalForCompany: array_sum($weeksForCompany),
                exitDate: $vehicle->exit_date?->toDateString(),
                weeksWithUnavailability: $this->collectWeeksWithUnavailability($vehicleUnavailabilities, $year),
                dailyRateCents: $pricing?->daily_rate_cents,
                weeklyRateCents: $pricing?->weekly_rate_cents,
                monthlyRateCents: $pricing?->monthly_rate_cents,
            );
        }

        $companyData = new CompanyOptionData(
            id: $company->id,
            shortCode: $company->short_code,
            legalName: $company->legal_name,
            color: $company->color,
        );

        $companyRows = $this->companies->findAllForHeatmap()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapCompanyVehicleData::collect($vehicleRows, DataCollection::class),
            'company' => $companyData,
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }

    /**
     * Per-vehicle theoretical full-year fiscal costs. Independent of
     * the company scope. Source ·
     * {@see FleetFiscalAggregator::vehicleFullYearTaxBreakdown()}
     * memoized per-request. Served as the "fast" `Inertia::defer`
     * group so the « Taxe pleine » column on the left of the heatmap
     * appears independently of the real annual tax (slower, see
     * {@see realCostsForVehicles()}).
     *
     * @return array<int, PlanningHeatmapVehicleFullYearCostsData>
     */
    public function fullYearCostsForVehicles(int $year): array
    {
        $vehicles = $this->vehicles->findAllForHeatmap($year);

        // Batch VFC prewarm · removes the N+1 queries when the
        // fiscal cache has no entry for these vehicles yet.
        $this->aggregator->prewarmVfcSegmentsForVehicles($vehicles->all(), $year);

        $costs = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            // Tolerates a year without coded fiscal rules · display
            // 0/0 instead of crashing the heatmap.
            try {
                $fullYear = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
                $fullYearTax = $fullYear->total;
                $dailyTaxRate = $fullYear->daysInYear > 0
                    ? round($fullYear->total / $fullYear->daysInYear, 2, PHP_ROUND_HALF_UP)
                    : 0.0;
            } catch (FiscalCalculationException) {
                $fullYearTax = 0.0;
                $dailyTaxRate = 0.0;
            }

            $costs[$vehicle->id] = new PlanningHeatmapVehicleFullYearCostsData(
                fullYearTax: $fullYearTax,
                dailyTaxRate: $dailyTaxRate,
            );
        }

        return $costs;
    }

    /**
     * Per-vehicle real annual taxes for the heatmap, scoped (per
     * company view) or global (overview) depending on `$companyId`.
     *
     * Source · {@see FleetFiscalAggregator::vehicleAnnualTax()}, NOT
     * cached (depends on contracts + unavailabilities + scope · complex
     * invalidation). ~3-5 ms per vehicle = ~200 ms on 64 vehicles.
     * Served as the "slow" `Inertia::defer` group so the « €XXXX · N j »
     * column on the right of the heatmap arrives independently of the
     * full-year column on the left.
     *
     * @return array<int, PlanningHeatmapVehicleRealCostsData>
     */
    public function realCostsForVehicles(int $year, ?int $companyId = null): array
    {
        $vehicles = $this->vehicles->findAllForHeatmap($year);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        // VFC prewarm batch · single SQL instead of the N+1 inside
        // the `vehicleAnnualTax` loop below.
        $this->aggregator->prewarmVfcSegmentsForVehicles($vehicles->all(), $year);

        $contractsByPair = $this->contracts->loadContractsByPair($year);
        if ($companyId !== null) {
            // Mirror the historical scoping of `buildHeatmapForCompany`
            // · keep only pairs for the requested company so
            // `vehicleAnnualTax` accounts for its contracts only.
            $contractsForCalc = new ContractsByPair(
                array_filter(
                    $contractsByPair->byPair,
                    static fn (string $key): bool => str_ends_with($key, '|'.$companyId),
                    ARRAY_FILTER_USE_KEY,
                ),
            );
        } else {
            $contractsForCalc = $contractsByPair;
        }

        $costs = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];

            // Tolerates a year without coded fiscal rules · the deferred
            // "realCosts" prop would otherwise throw and leave the heatmap
            // tax column in an infinite skeleton. Mirrors the guard in
            // {@see fullYearCostsForVehicles()}.
            try {
                $annualTaxDue = $this->aggregator->vehicleAnnualTax(
                    $vehicle,
                    $contractsForCalc,
                    $vehicleUnavailabilities,
                    $year,
                );
            } catch (FiscalCalculationException) {
                $annualTaxDue = 0.0;
            }

            $costs[$vehicle->id] = new PlanningHeatmapVehicleRealCostsData(
                annualTaxDue: $annualTaxDue,
            );
        }

        return $costs;
    }

    /**
     * Monthly NET rental totals (post-discount) for a company on a
     * year · `array<month [1..12], ?int totalCents>`. Returns `null`
     * for a month when at least one vehicle present that month lacks
     * a yearly pricing (explicit UX signal in the heatmap header).
     *
     * Slim by design (no full 12-month timeline, no emitted invoices,
     * no detailed entries) · served as the "rentals" `Inertia::defer`
     * group.
     *
     * @return array<int, ?int> month (1-12) → net cents (`null` if pricing missing)
     */
    public function monthlyRentalTotalsForCompany(int $year, int $companyId): array
    {
        $monthlyResults = $this->billingCalculator->calculateYear($companyId, $year);
        $discountIndex = $this->discountResolver->preloadForCompanyYear($companyId, $year);
        $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $discountIndex);

        $totals = [];
        for ($month = 1; $month <= 12; $month++) {
            $result = $monthlyResults[$month];
            $totals[$month] = $result instanceof MissingPricingException
                ? null
                : $result->totalCents;
        }

        return $totals;
    }

    /**
     * Monthly NET rental totals across the fleet (cross-company) ·
     * 3 fixed SQL queries regardless of company count ·
     *   1. Active contracts crossing the year (vehicle eager-loaded
     *      in-memory).
     *   2. Vehicles by ids (with `exit_date` for ADR-0018 clipping).
     *   3. Pricings batched `(vehicleIds × year)`.
     * Plus one SQL for multi-company active discounts.
     *
     * For each company we compute independently via
     * `OptimalRateBreakdown`, then apply that company's discounts.
     * The cross-company sum happens in memory (zero query). When a
     * month has a partial missing pricing we only sum the companies
     * without a missing pricing (aligned with
     * `BillingBreakdownService::totalRecettesForYears`).
     *
     * @return array<int, int> month (1-12) → net cents (0 when no contract that month)
     */
    public function monthlyRentalTotalsForFleet(int $year): array
    {
        $contracts = $this->contractReader->findActiveForYearRange($year, $year);

        if ($contracts->isEmpty()) {
            return array_fill_keys(range(1, 12), 0);
        }

        $vehicleIdsSet = [];
        foreach ($contracts as $contract) {
            $vehicleIdsSet[(int) $contract->vehicle_id] = true;
        }
        $vehicleIdList = array_keys($vehicleIdsSet);

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);

        foreach ($contracts as $contract) {
            $contract->setRelation('vehicle', $vehiclesById->get($contract->vehicle_id));
        }

        $pricingsByVehicle = $this->pricings->findForVehiclesAndYear($vehicleIdList, $year);

        $byCompany = [];
        foreach ($contracts as $contract) {
            $byCompany[(int) $contract->company_id][] = $contract;
        }

        $discountIndexByCompany = $this->discountResolver
            ->preloadForCompaniesYear(array_keys($byCompany), $year);

        $totals = array_fill_keys(range(1, 12), 0);

        foreach ($byCompany as $companyId => $companyContracts) {
            $monthlyResults = $this->billingCalculator->calculateYearWithPreloaded(
                $companyId,
                $year,
                $companyContracts,
                $pricingsByVehicle,
            );
            $index = $discountIndexByCompany[$companyId] ?? ResolvedDiscountIndex::empty();
            $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $index);

            for ($month = 1; $month <= 12; $month++) {
                $result = $monthlyResults[$month];
                if (! $result instanceof MissingPricingException) {
                    $totals[$month] += $result->totalCents;
                }
            }
        }

        return $totals;
    }

    /**
     * Sorted, deduplicated list of ISO week numbers (1-52) where at
     * least one unavailability day (any type) falls within the
     * fiscal year. Feeds the red border around heatmap cells
     * (ADR-0019) for instant visibility on unavailability ↔ contract
     * cohabitation.
     *
     * @param  list<Unavailability>  $unavailabilities
     * @return list<int>
     */
    private function collectWeeksWithUnavailability(array $unavailabilities, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();

        $weeks = [];
        foreach ($unavailabilities as $unavailability) {
            if ($unavailability->start_date->greaterThan($yearEnd)) {
                continue;
            }
            if ($unavailability->end_date !== null && $unavailability->end_date->lessThan($yearStart)) {
                continue;
            }

            $start = $unavailability->start_date->greaterThan($yearStart)
                ? $unavailability->start_date
                : $yearStart;
            $end = $unavailability->end_date === null || $unavailability->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $unavailability->end_date;

            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $weeks[(int) $cursor->isoWeek] = true;
                }
                $cursor = $cursor->addDay();
            }
        }

        $list = array_keys($weeks);
        sort($list);

        return $list;
    }
}
