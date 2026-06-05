<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyActivityYearData;
use App\Data\User\Company\CompanyDetailData;
use App\Data\User\Company\CompanyDriverRowData;
use App\Data\User\Company\CompanyEditData;
use App\Data\User\Company\CompanyLifetimeStatsData;
use App\Data\User\Company\CompanyTopVehicleData;
use App\Data\User\Company\CompanyYearStatsData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Pivot\DriverCompany;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Support\Carbon;

/**
 * Detail service for the Company Show page, extracted from
 * `CompanyQueryService` for SRP.
 *
 * Carries the most complex flow of the Company domain, aggregating ·
 *   - identity hero (timeless)
 *   - lifetime KPIs (cumulative)
 *   - "Historique par année" section
 *   - per-year sub-stats (monthly heatmap + top 3 vehicles)
 *   - driver list with active memberships + departures
 *
 * Three temporal lenses ·
 *   - Present · top-of-page KPIs (current calendar year).
 *   - Lifetime · cumulative KPIs across every year.
 *   - History · one row per year in the global scope, even when the
 *     company has no contract that year (zeros row).
 */
final class CompanyDetailService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly ContractReadRepositoryInterface $contractsRepo,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
        private readonly RentalPriceCalculator $rentalPrice,
    ) {}

    /**
     * Slim projection for the Edit page · the 14 scalar identity /
     * address / contact fields, without triggering the fiscal pipeline
     * nor any multi-year aggregation.
     *
     * Saves ~280 ms cold versus {@see detail()} (which runs drivers +
     * lifetime + history + activityByYear with the full fiscal pipeline).
     */
    public function detailForEdit(int $companyId): ?CompanyEditData
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            return null;
        }

        return new CompanyEditData(
            id: $company->id,
            legalName: $company->legal_name,
            shortCode: $company->short_code,
            color: $company->color,
            siren: $company->siren,
            siret: $company->siret,
            addressLine1: $company->address_line_1,
            addressLine2: $company->address_line_2,
            postalCode: $company->postal_code,
            city: $company->city,
            country: $company->country,
            contactName: $company->contact_name,
            contactEmail: $company->contact_email,
            contactPhone: $company->contact_phone,
        );
    }

    /**
     * Full detail for the Show page · identity hero, lifetime KPIs,
     * history, per-year activity, drivers list.
     *
     * `currentRealYear` lets the history mark the in-progress fiscal
     * exercise on the frontend without relying on `new Date()`.
     */
    public function detail(int $companyId): ?CompanyDetailData
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            return null;
        }

        $today = Carbon::today();
        $currentRealYear = (int) $today->year;

        $company->load(['drivers' => function ($query): void {
            $query->orderByPivot('joined_at');
        }]);

        // Per-driver contract count in this company. The N:N pivot
        // `contract_drivers` is used · a contract with two drivers
        // counts once for each. Aggregation delegated to the repository
        // (ADR-0013 R3 · no direct SQL in services).
        $contractsCountByDriver = $this->contractsRepo->countContractsByDriverForCompany($companyId);

        $driverRows = $company->drivers->map(function ($driver) use ($contractsCountByDriver): CompanyDriverRowData {
            /** @var DriverCompany $pivot */
            $pivot = $driver->getAttribute('pivot');
            $first = (string) ($driver->first_name ?? '');
            $last = (string) ($driver->last_name ?? '');
            $fullName = trim($first.' '.$last);
            $initials = mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));

            return new CompanyDriverRowData(
                driverId: $driver->id,
                pivotId: $pivot->id,
                fullName: $fullName !== '' ? $fullName : '-',
                initials: $initials !== '' ? $initials : '-',
                joinedAt: $pivot->joined_at->toDateString(),
                leftAt: $pivot->left_at?->toDateString(),
                // Matches the write-side `findActiveMembership` semantic
                // (`left_at IS NULL`). Mirrors `DriverQueryService::detail`.
                isCurrentlyActive: $pivot->left_at === null,
                contractsCount: (int) ($contractsCountByDriver[$driver->id] ?? 0),
            );
        })->values()->all();

        $activeDriversCount = 0;
        foreach ($driverRows as $row) {
            if ($row->isCurrentlyActive) {
                $activeDriversCount++;
            }
        }

        $contractsCount = $this->contracts->countContractsForCompany($companyId);
        $availableYears = $this->contracts->findActiveYearsForCompany($companyId);

        // Single bulk load over the year range instead of 2×N year-by-year
        // calls to `loadContractsByPair()` from `computeYearStats` and
        // `computeActivityForYear`. The pre-resolved pivot is forwarded
        // to both sub-methods to short-circuit their internal loading.
        $contractsByYear = [];
        if ($availableYears !== []) {
            $contractsByYear = $this->contracts->loadContractsByPairForYearRange(
                min($availableYears),
                max($availableYears),
            );
        }

        $allYearStats = [];
        foreach ($availableYears as $year) {
            $allYearStats[$year] = $this->computeYearStats(
                $companyId,
                $year,
                $contractsByYear[$year] ?? null,
            );
        }

        $lifetime = new CompanyLifetimeStatsData(
            daysUsed: array_sum(array_map(static fn (CompanyYearStatsData $s): int => $s->daysUsed, $allYearStats)),
            contractsCount: $contractsCount,
            taxesGenerated: round(
                array_sum(array_map(static fn (CompanyYearStatsData $s): float => $s->annualTaxDue, $allYearStats)),
                2,
                PHP_ROUND_HALF_UP,
            ),
            // Rent total exposed as a nullable placeholder until the
            // billing module is wired in. The UI renders the KPI card
            // already; the real value lights up once available.
            rentTotal: null,
        );

        // Present · top-of-page KPIs, always on the current calendar
        // year. If the company has no contract that year, a neutral
        // CompanyYearStatsData (zeros) is returned so the UI renders
        // "0 j / 0 contrats / 0 €" without crashing.
        $kpiYear = $this->availableYears->currentYear();
        $kpiStats = $allYearStats[$kpiYear] ?? $this->emptyYearStats($kpiYear);

        // Distinguishes "no data" (zero KPIs) from "fiscal computation
        // unavailable" (rules not yet coded for `kpiYear`). Lets the UI
        // surface a short explicit message on the Taxes KPI only.
        $kpiFiscalAvailable = in_array(
            $kpiYear,
            $this->fiscalRules->registeredYears(),
            true,
        );

        // History · every past year in the global scope
        // `[minYear..kpiYear-1]`, including years where the company has
        // no contract (neutral zero rows). A zero year on a company
        // fiche is informative ("this year nothing was used"). Consistent
        // with the global-bounds doctrine shared across screens. When
        // the DB is empty globally, `minYear == kpiYear` → empty loop
        // → UI empty state.
        $historyMinYear = $this->availableYears->minYear();
        $history = [];
        for ($year = $historyMinYear; $year < $kpiYear; $year++) {
            $history[] = $allYearStats[$year] ?? $this->emptyYearStats($year);
        }

        return new CompanyDetailData(
            id: $company->id,
            legalName: $company->legal_name,
            shortCode: $company->short_code,
            color: $company->color,
            siren: $company->siren,
            siret: $company->siret,
            addressLine1: $company->address_line_1,
            addressLine2: $company->address_line_2,
            postalCode: $company->postal_code,
            city: $company->city,
            country: $company->country,
            contactName: $company->contact_name,
            contactEmail: $company->contact_email,
            contactPhone: $company->contact_phone,
            isActive: $company->is_active,
            isOig: $company->is_oig,
            isIndividualBusiness: $company->is_individual_business,
            contractsCount: $contractsCount,
            activeDriversCount: $activeDriversCount,
            totalDriversCount: count($driverRows),
            drivers: $driverRows,
            lifetime: $lifetime,
            kpiStats: $kpiStats,
            kpiYear: $kpiYear,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            history: $history,
            activityByYear: array_map(
                fn (int $year): CompanyActivityYearData => $this->computeActivityForYear(
                    $companyId,
                    $year,
                    $contractsByYear[$year] ?? null,
                ),
                $availableYears,
            ),
            availableYears: $availableYears,
            currentRealYear: $currentRealYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Computes the per-year activity detail · 12-month heatmap (vehicle-days
     * per month) and top 3 vehicles (descending by used days). Vehicle
     * lookup is bulked to avoid N+1. Returns neutral data (zero heatmap
     * + empty top) when the company has no pair for the year.
     *
     * `$preloadedPair` lets {@see detail()} feed the pivot resolved in
     * bulk via `loadContractsByPairForYearRange()`, saving N
     * `loadContractsByPair($year)` calls.
     */
    private function computeActivityForYear(
        int $companyId,
        int $year,
        ?ContractsByPair $preloadedPair = null,
    ): CompanyActivityYearData {
        $contractsByPair = $preloadedPair ?? $this->contracts->loadContractsByPair($year);

        // Two-pass accumulator · by vehicle (for the top), by month (for
        // the heatmap), driven by the company's pairs over the year.
        // One vehicle-day = one unit; two vehicles attributed on the
        // same day = two units on that month's counter.
        /** @var array<int, int> $daysPerVehicle */
        $daysPerVehicle = [];
        $daysByMonth = array_fill(0, 12, 0);

        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            foreach ($pairContracts as $contract) {
                foreach ($contract->expandToDaysInYear($year) as $iso) {
                    $monthIndex = (int) substr($iso, 5, 2) - 1;
                    $daysByMonth[$monthIndex]++;
                    $daysPerVehicle[$vehicleId] = ($daysPerVehicle[$vehicleId] ?? 0) + 1;
                }
            }
        }

        if ($daysPerVehicle === []) {
            return new CompanyActivityYearData(
                year: $year,
                daysByMonth: $daysByMonth,
                topVehicles: [],
            );
        }

        arsort($daysPerVehicle);
        $topVehicleIds = array_slice(array_keys($daysPerVehicle), 0, 3, preserve_keys: true);

        $vehiclesById = $this->vehicles->findByIdsIndexed($topVehicleIds);

        $totalVehicleDays = (int) array_sum($daysPerVehicle);

        $topVehicles = [];
        foreach ($topVehicleIds as $vehicleId) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $days = $daysPerVehicle[$vehicleId];
            $topVehicles[] = new CompanyTopVehicleData(
                vehicleId: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $days,
                percentage: $totalVehicleDays > 0
                    ? round($days / $totalVehicleDays * 100, 1)
                    : 0.0,
            );
        }

        return new CompanyActivityYearData(
            year: $year,
            daysByMonth: $daysByMonth,
            topVehicles: $topVehicles,
        );
    }

    /**
     * Neutral stats (all counters zero) for a year where the company
     * has no contract. Used for KPIs and to fill history gaps.
     */
    private function emptyYearStats(int $year): CompanyYearStatsData
    {
        return new CompanyYearStatsData(
            year: $year,
            daysUsed: 0,
            contractsCount: 0,
            lcdCount: 0,
            lldCount: 0,
            annualTaxDue: 0.0,
            rent: null,
        );
    }

    /**
     * Yearly KPIs for one company. Loads the year contracts (cross-fleet
     * via aggregator) then filters on the `(vehicleId, $companyId)` pair
     * on the `ContractsByPair` side.
     */
    private function computeYearStats(
        int $companyId,
        int $year,
        ?ContractsByPair $preloadedPair = null,
    ): CompanyYearStatsData {
        $contractsByPair = $preloadedPair ?? $this->contracts->loadContractsByPair($year);

        $vehicleIds = [];
        $lcdCount = 0;
        $lldCount = 0;
        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicleIds[] = $vehicleId;
            foreach ($pairContracts as $contract) {
                if ($contract->contract_type === ContractType::Lcd) {
                    $lcdCount++;
                } else {
                    $lldCount++;
                }
            }
        }

        $daysUsed = $contractsByPair->daysByCompany($companyId, $year);

        $annualTaxDue = 0.0;
        if ($vehicleIds !== []) {
            try {
                $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
                $vehicleEventsByVehicleId = $this->contracts->loadVehicleEventsByVehicle($vehicleIds);
                // Charge en un seul SQL les segments VFC de l'année pour tous les
                // véhicules, au lieu d'une requête VFC par véhicule dans le pipeline.
                $this->aggregator->prewarmVfcSegmentsForVehicles($vehiclesById, $year);
                $annualTaxDue = $this->aggregator->companyAnnualTax(
                    $companyId,
                    $vehiclesById,
                    $contractsByPair,
                    $vehicleEventsByVehicleId,
                    $year,
                );
            } catch (FiscalCalculationException) {
                // Year not registered in the fiscal calculator. We keep
                // `annualTaxDue: 0.0` rather than crashing the page · the
                // user still sees days and contracts count. Typical for
                // contracts predating or postdating the supported range.
                $annualTaxDue = 0.0;
            }
        }

        $rentCents = $this->rentalPrice->forCompanyAndYear($companyId, $year);
        $rent = $rentCents === null ? null : $rentCents / 100;

        return new CompanyYearStatsData(
            year: $year,
            daysUsed: $daysUsed,
            contractsCount: $lcdCount + $lldCount,
            lcdCount: $lcdCount,
            lldCount: $lldCount,
            annualTaxDue: $annualTaxDue,
            rent: $rent,
        );
    }
}
