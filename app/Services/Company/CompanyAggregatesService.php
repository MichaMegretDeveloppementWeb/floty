<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Billing\MonthlyBillingBreakdownData;
use App\Data\User\Company\CompanyFiscalYearData;
use App\Data\User\Company\CompanyVehicleFiscalRowData;
use App\Data\User\RentalDiscount\RentalDiscountListItemData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\RentalDiscount;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Carbon;

/**
 * Yearly aggregates feeding the Company Show tabs, extracted from
 * `CompanyQueryService` for SRP.
 *
 *   - `billingForYear` · Billing tab (monthly billing recap).
 *   - `fiscalBreakdownForYear` · Fiscal tab (one row per used
 *     vehicle, aggregated CO₂ + pollutants totals).
 *   - `rentalDiscountsForCompany` · commercial discounts list.
 */
final class CompanyAggregatesService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly BillingBreakdownService $billingBreakdown,
        private readonly RentalDiscountReadRepositoryInterface $rentalDiscounts,
        private readonly FiscalYearContext $yearContext,
    ) {}

    /**
     * Monthly billing recap for a company and a year. Separating
     * `BillingBreakdownService` aggregation from the main `detail()`
     * flow allows the UI to carry an independent year selector
     * (pattern mirroring `fiscalBreakdownForYear`).
     */
    public function billingForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        return $this->billingBreakdown->byCompanyForYear($companyId, $year);
    }

    /**
     * Commercial discounts list for a company (planned + active +
     * expired, sorted `start_date DESC`). Feeds the dedicated section
     * of the Billing tab. Slim DTO · the full Show page lives on the
     * standalone RentalDiscount route.
     *
     * @return list<RentalDiscountListItemData>
     */
    public function rentalDiscountsForCompany(int $companyId): array
    {
        return $this->rentalDiscounts
            ->findForCompany($companyId)
            ->load('company:id,short_code,legal_name,color')
            ->map(static fn (RentalDiscount $d): RentalDiscountListItemData => RentalDiscountListItemData::fromModel($d))
            ->all();
    }

    /**
     * Fiscal detail for the selected year · one row per used vehicle,
     * aggregated totals (R-2024-003 · single rounding per taxpayer).
     *
     * When the year is not registered in the fiscal engine, returns
     * zeroed amounts rather than crashing · the user still sees days
     * and vehicle counts.
     */
    public function fiscalBreakdownForYear(int $companyId, int $year): CompanyFiscalYearData
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $currentRealYear = (int) Carbon::now()->year;
        $fiscalYearSupported = $this->yearContext->isSupported($year);
        $availableYears = $this->contracts->availableYearsRangeForCompany(
            $companyId,
            $currentRealYear,
        );

        $vehicleIds = [];
        $daysPerVehicle = [];
        $totalContracts = 0;
        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicleIds[] = $vehicleId;
            $totalContracts += count($pairContracts);
            // Pre-compute days independently of the fiscal pipeline ·
            // used for the `daysUsed` column even when the fiscal
            // year is not configured (FiscalCalculationException).
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += $contract->countDaysInYear($year);
            }
            $daysPerVehicle[$vehicleId] = $days;
        }

        if ($vehicleIds === []) {
            return new CompanyFiscalYearData(
                year: $year,
                currentRealYear: $currentRealYear,
                fiscalYearSupported: $fiscalYearSupported,
                rows: [],
                availableYears: $availableYears,
                totalDays: 0,
                totalTaxCo2: 0.0,
                totalTaxPollutants: 0.0,
                totalTaxAll: 0.0,
                contractsCount: 0,
            );
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
        $vehicleEventsByVehicleId = $this->contracts->loadVehicleEventsByVehicle($vehicleIds);
        // Charge en un seul SQL les segments VFC de l'année pour tous les
        // véhicules, au lieu d'une requête VFC par véhicule dans le pipeline.
        $this->aggregator->prewarmVfcSegmentsForVehicles($vehiclesById, $year);

        // Wrap fiscal pipeline · tolerates years without registered
        // fiscal rules.
        $taxRowsByVehicleId = [];
        try {
            $rawRows = $this->aggregator->companyAnnualTaxBreakdownByVehicle(
                $companyId,
                $vehiclesById,
                $contractsByPair,
                $vehicleEventsByVehicleId,
                $year,
            );
            foreach ($rawRows as $rawRow) {
                $taxRowsByVehicleId[$rawRow['vehicleId']] = $rawRow;
            }
        } catch (FiscalCalculationException) {
            $taxRowsByVehicleId = [];
        }

        $daysInYear = Carbon::createFromDate($year, 1, 1)->isLeapYear() ? 366 : 365;

        $rows = [];
        $totalDays = 0;
        $totalTaxCo2Raw = 0.0;
        $totalTaxPollutantsRaw = 0.0;

        foreach ($vehicleIds as $vehicleId) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }

            // `daysUsed` always taken from the raw pre-compute (days
            // attributed within the year), not from the pipeline's
            // `daysAssigned`, which might be reduced by R-2024-008
            // (unavailabilities) or R-2024-021 (LCD < 30 d out of
            // period). We display the consumed days.
            $days = (int) ($daysPerVehicle[$vehicleId] ?? 0);
            $taxRow = $taxRowsByVehicleId[$vehicleId] ?? null;
            $taxCo2 = $taxRow !== null ? (float) $taxRow['taxCo2'] : 0.0;
            $taxPollutants = $taxRow !== null ? (float) $taxRow['taxPollutants'] : 0.0;
            $taxTotal = $taxRow !== null ? (float) $taxRow['taxTotal'] : 0.0;

            $proratoPercent = round($days / $daysInYear * 100, 1);

            $rows[] = new CompanyVehicleFiscalRowData(
                vehicleId: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $days,
                proratoPercent: $proratoPercent,
                taxCo2: $taxCo2,
                taxPollutants: $taxPollutants,
                taxTotal: $taxTotal,
            );

            $totalDays += $days;
            $totalTaxCo2Raw += $taxCo2;
            $totalTaxPollutantsRaw += $taxPollutants;
        }

        $totalTaxCo2 = round($totalTaxCo2Raw, 2, PHP_ROUND_HALF_UP);
        $totalTaxPollutants = round($totalTaxPollutantsRaw, 2, PHP_ROUND_HALF_UP);

        return new CompanyFiscalYearData(
            year: $year,
            currentRealYear: $currentRealYear,
            fiscalYearSupported: $fiscalYearSupported,
            rows: $rows,
            availableYears: $availableYears,
            totalDays: $totalDays,
            totalTaxCo2: $totalTaxCo2,
            totalTaxPollutants: $totalTaxPollutants,
            totalTaxAll: round($totalTaxCo2 + $totalTaxPollutants, 2, PHP_ROUND_HALF_UP),
            contractsCount: $totalContracts,
        );
    }
}
