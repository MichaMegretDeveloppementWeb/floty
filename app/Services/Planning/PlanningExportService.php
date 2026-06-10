<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Planning\PlanningExportRequestData;
use App\DTO\Fiscal\ContractsByPair;
use App\DTO\Planning\PlanningExportData;
use App\DTO\Planning\PlanningExportRowData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Support\Date\IsoWeeks;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Assembles the render context for a planning PDF export, scoped to the
 * vehicles selected in the modal.
 *
 * Authoritative by design · the weekly usage and every amount are
 * recomputed here from the fiscal engine; nothing is trusted from the
 * client payload (which only carries ids + year + mode + scope).
 *
 * Mirrors the row-assembly of {@see PlanningHeatmapService} but for a
 * different (export) shape · it carries BOTH the full-year and real tax
 * per row plus the fiche fields (1re immat., pollutant category), and is
 * bounded to the selected ids instead of the whole active fleet.
 */
final readonly class PlanningExportService
{
    public function __construct(
        private VehicleReadRepositoryInterface $vehicles,
        private CompanyReadRepositoryInterface $companies,
        private ContractQueryService $contracts,
        private FleetFiscalAggregator $aggregator,
        private VehicleYearlyPricingReadRepositoryInterface $pricings,
    ) {}

    public function build(PlanningExportRequestData $request): PlanningExportData
    {
        $year = $request->year;
        $companyId = $request->companyId;

        $vehicles = $this->vehicles->findByIdsForHeatmap($request->vehicleIds, $year);
        $vehicleIds = $vehicles->pluck('id')->all();

        $weekDensity = $companyId !== null
            ? $this->contracts->loadWeekDensityForCompany($year, $companyId)
            : $this->contracts->loadWeekDensity($year);

        $pricingsByVehicleId = $this->pricings->findForVehiclesAndYear($vehicleIds, $year);
        $vehicleEventsByVehicleId = $this->contracts->loadVehicleEventsByVehicle($vehicleIds);

        // Batch VFC prewarm · removes the N+1 inside the per-vehicle
        // aggregator calls below.
        $this->aggregator->prewarmVfcSegmentsForVehicles($vehicles->all(), $year);

        $contractsForCalc = $this->contractsForCalculation($year, $companyId);

        $weeksCount = IsoWeeks::CELLS_PER_YEAR;
        $rows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeks = [];
            for ($w = 1; $w <= $weeksCount; $w++) {
                $weeks[] = $weekDensity[$vehicle->id.'|'.$w] ?? 0;
            }

            // Tolerates a year without coded fiscal rules · 0 instead of
            // crashing the export (mirrors the heatmap deferred guards).
            // `appliedExemptions` is the vehicle-level exemption set for the
            // year (same source as the Vehicle Show « Exonérations
            // applicables » panel).
            try {
                $fullYear = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
                $fullYearTax = $fullYear->total;
                $exemptions = array_values(array_unique(array_map(
                    static fn (AppliedExemptionData $exemption): string => $exemption->reason,
                    $fullYear->appliedExemptions,
                )));
            } catch (FiscalCalculationException) {
                $fullYearTax = 0.0;
                $exemptions = [];
            }

            try {
                $annualTaxDue = $this->aggregator->vehicleAnnualTax(
                    $vehicle,
                    $contractsForCalc,
                    $vehicleEventsByVehicleId[$vehicle->id] ?? [],
                    $year,
                );
            } catch (FiscalCalculationException) {
                $annualTaxDue = 0.0;
            }

            $pricing = $pricingsByVehicleId[$vehicle->id] ?? null;

            $rows[] = new PlanningExportRowData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                pollutantCategory: $fiscal->pollutant_category,
                firstFrenchRegistrationDate: $vehicle->first_french_registration_date->toDateString(),
                weeks: $weeks,
                weeksOutOfFleet: $this->weeksOutOfFleet($vehicle->exit_date, $year, $weeksCount),
                daysTotal: array_sum($weeks),
                fullYearTax: $fullYearTax,
                annualTaxDue: $annualTaxDue,
                dailyRateCents: $pricing?->daily_rate_cents,
                weeklyRateCents: $pricing?->weekly_rate_cents,
                monthlyRateCents: $pricing?->monthly_rate_cents,
                exemptions: $exemptions,
                exitDate: $vehicle->exit_date?->toDateString(),
                exitReason: $vehicle->exit_reason,
            );
        }

        $company = $companyId !== null ? $this->companies->findById($companyId) : null;

        return new PlanningExportData(
            companyName: $company?->legal_name,
            companyShortCode: $company?->short_code,
            year: $year,
            mode: $request->mode,
            generatedAt: CarbonImmutable::now(),
            rows: $rows,
        );
    }

    /**
     * Contracts feeding the real annual tax · all pairs (overview) or
     * only the requested company's pairs (per-company scope). Mirrors the
     * scoping of {@see PlanningHeatmapService::realCostsForVehicles()}.
     */
    private function contractsForCalculation(int $year, ?int $companyId): ContractsByPair
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        if ($companyId === null) {
            return $contractsByPair;
        }

        return new ContractsByPair(array_filter(
            $contractsByPair->byPair,
            static fn (string $key): bool => str_ends_with($key, '|'.$companyId),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    /**
     * Per-week (0-based) "out of fleet" flags for the complete-grid greying.
     * Mirrors the heatmap's `isCellAfterExit`: a week is out only when its
     * number is strictly greater than the ISO week of the exit date (the
     * exit week itself stays in fleet), scoped to the exit year (before the
     * year · all out; after · none).
     *
     * @return list<bool>
     */
    private function weeksOutOfFleet(?CarbonInterface $exitDate, int $year, int $weeksCount): array
    {
        if ($exitDate === null) {
            return array_fill(0, $weeksCount, false);
        }

        $exitYear = (int) $exitDate->year;
        if ($exitYear < $year) {
            return array_fill(0, $weeksCount, true);
        }
        if ($exitYear > $year) {
            return array_fill(0, $weeksCount, false);
        }

        $exitIsoWeek = (int) $exitDate->isoWeek;
        $flags = [];
        for ($w = 1; $w <= $weeksCount; $w++) {
            $flags[] = $w > $exitIsoWeek;
        }

        return $flags;
    }
}
