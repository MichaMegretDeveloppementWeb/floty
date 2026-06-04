<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\User\Billing\ContractBillingBreakdownData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Fiscal\FiscalBreakdownData;
use App\Data\User\Fiscal\FiscalPreviewData;
use App\Data\User\Planning\PlanningWeekData;
use App\Data\User\Planning\PreviewRentalsInputData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\RentalMonthlyImpactData;
use App\Data\User\Planning\RentalPreviewData;
use App\Data\User\Planning\WeekCompanyPresenceData;
use App\Data\User\Planning\WeekDayContractData;
use App\Data\User\Planning\WeekDaySlotData;
use App\Enums\Contract\ContractType;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Contract;
use App\Models\RentalDiscount;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\Discount\DiscountApplier;
use App\Services\Billing\Discount\DiscountResolver;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FiscalCalculator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use App\Support\Date\IsoWeeks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Week detail for the planning drawer plus the tax / rental previews
 * for a new-contract creation.
 *
 * The preview simulates the addition of a synthetic contract on the
 * `[min(dates), max(dates)]` range · semantically aligned with the
 * range selection of the `DateRangePicker`.
 */
final class WeekDetailService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contractQuery,
        private readonly VehicleEventReadRepositoryInterface $vehicleEventRepo,
        private readonly FiscalCalculator $calculator,
        private readonly FiscalYearContext $yearContext,
        private readonly BillingBreakdownService $billingBreakdown,
        private readonly DiscountResolver $discountResolver,
        private readonly BillingCalculator $billingCalculator,
        private readonly DiscountApplier $discountApplier,
    ) {}

    /**
     * Drawer payload for a given vehicle week. Lists the days; for
     * each day reports the active contract covering it (one contract
     * max per day thanks to the anti-overlap trigger).
     */
    public function buildWeek(int $vehicleId, int $weekNumber, int $year): PlanningWeekData
    {
        $vehicle = $this->vehicles->findOrFailWithFiscal($vehicleId);

        // `$weekNumber` is a cell position (1..53) in the heatmap
        // year · the matching Monday is derived from
        // `IsoWeeks::cellOriginForYear()` plus the offset.
        $origin = IsoWeeks::cellOriginForYear($year);
        $start = Carbon::instance($origin->addDays(($weekNumber - 1) * 7));
        $end = $start->copy()->addDays(6);

        $weekContracts = $this->contractQuery->findWindowContractsForVehicle(
            $vehicleId,
            $start,
            $end,
        );

        // For the red border around unavailability days in the
        // « État de la semaine » grid · load the vehicle's
        // unavailabilities crossing the week window once and filter
        // per day in PHP (ADR-0019).
        $weekVehicleEvents = $this->vehicleEventRepo
            ->findForVehicle($vehicleId)
            ->filter(static fn ($u): bool => $u->implies_unavailability
                && $u->start_date->lessThanOrEqualTo($end)
                && ($u->end_date === null || $u->end_date->greaterThanOrEqualTo($start)));

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $iso = $cursor->toDateString();
            $contract = $weekContracts->first(
                static fn (Contract $c): bool => $iso >= $c->start_date->toDateString()
                    && $iso <= $c->end_date->toDateString(),
            );

            $hasVehicleEventOnDay = $weekVehicleEvents->contains(
                static fn ($u): bool => $u->start_date->toDateString() <= $iso
                    && ($u->end_date === null || $u->end_date->toDateString() >= $iso),
            );

            $days[] = new WeekDaySlotData(
                date: $iso,
                dayLabel: $cursor->translatedFormat('D d'),
                contract: $contract !== null
                    ? new WeekDayContractData(
                        id: $contract->id,
                        company: new CompanyOptionData(
                            id: $contract->company->id,
                            shortCode: $contract->company->short_code,
                            legalName: $contract->company->legal_name,
                            color: $contract->company->color,
                        ),
                    )
                    : null,
                hasVehicleEvent: $hasVehicleEventOnDay,
            );
            $cursor->addDay();
        }

        $companiesOnWeek = $this->buildCompaniesOnWeek($weekContracts, $start, $end);

        // Every year date already booked for the vehicle · prevents
        // conflicting selection in the DateRangePicker even when the
        // user navigates through other weeks/months than the drawer
        // origin.
        $vehicleBusyDates = $this->contractQuery->findDatesForVehicleInRange(
            $vehicleId,
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
        );

        return new PlanningWeekData(
            weekNumber: $weekNumber,
            weekStart: $start->toDateString(),
            weekEnd: $end->toDateString(),
            vehicleId: $vehicle->id,
            licensePlate: $vehicle->license_plate,
            days: $days,
            companiesOnWeek: $companiesOnWeek,
            vehicleBusyDates: $vehicleBusyDates,
        );
    }

    /**
     * Company-scoped variant of {@see buildWeek()} · anonymises
     * contracts of other companies in the week grid and filters
     * `companiesOnWeek` to keep only the requested company.
     *
     * Security · the frontend never receives the identity or colour
     * of the occupying company when the contract is not its own.
     */
    public function buildWeekForCompany(
        int $vehicleId,
        int $weekNumber,
        int $year,
        int $companyId,
    ): PlanningWeekData {
        $week = $this->buildWeek($vehicleId, $weekNumber, $year);

        $anonymizedDays = array_map(
            static function (WeekDaySlotData $slot) use ($companyId): WeekDaySlotData {
                if ($slot->contract === null) {
                    return $slot;
                }

                if ($slot->contract->company->id === $companyId) {
                    return $slot;
                }

                return new WeekDaySlotData(
                    date: $slot->date,
                    dayLabel: $slot->dayLabel,
                    contract: null,
                    hasVehicleEvent: $slot->hasVehicleEvent,
                    isOccupiedByOther: true,
                );
            },
            $week->days,
        );

        $filteredCompaniesOnWeek = array_values(array_filter(
            $week->companiesOnWeek,
            static fn (WeekCompanyPresenceData $entry): bool => $entry->company->id === $companyId,
        ));

        return new PlanningWeekData(
            weekNumber: $week->weekNumber,
            weekStart: $week->weekStart,
            weekEnd: $week->weekEnd,
            vehicleId: $week->vehicleId,
            licensePlate: $week->licensePlate,
            days: $anonymizedDays,
            companiesOnWeek: $filteredCompaniesOnWeek,
            vehicleBusyDates: $week->vehicleBusyDates,
        );
    }

    /**
     * Standalone fiscal preview of an assignment.
     *
     * LCD/LLD is qualified contract-by-contract individually from the
     * contract length alone (≤ 30 days → LCD, otherwise LLD). No
     * notion of annual cumul per `(vehicle × company)`. The preview
     * therefore computes the strict fiscal cost of this contract
     * exactly · its days, CO₂, pollutants, total, applicable
     * exemptions.
     *
     * We simulate a single synthetic contract on `[min(dates),
     * max(dates)]` without accounting for other contracts on the
     * same pair. When the range partially overlaps an existing
     * contract the preview stays indicative · the actual creation
     * goes through `BulkCreateContractsAction` which detects the
     * overlap.
     */
    public function previewTaxes(PreviewTaxesInputData $input, int $year): FiscalPreviewData
    {
        $yearPrefix = $year.'-';

        $newDates = array_values(array_filter(
            $input->dates,
            static fn (string $d): bool => str_starts_with($d, $yearPrefix),
        ));
        sort($newDates);

        $syntheticContract = $newDates === []
            ? null
            : $this->buildSyntheticContract(
                $input->vehicleId,
                $input->companyId,
                $newDates[0],
                $newDates[count($newDates) - 1],
            );

        $daysCount = $syntheticContract?->countDaysInYear($year) ?? 0;

        // Year without coded fiscal rules · no tax is computed. Return a
        // neutral payload (200, `supported: false`) so the wizard shows a
        // "no fiscal rules" note instead of a recurring error toast. The
        // rental preview lives on its own endpoint and stays unaffected.
        if (! $this->yearContext->isSupported($year)) {
            return new FiscalPreviewData(
                fiscalYear: $year,
                daysCount: $daysCount,
                breakdown: null,
                supported: false,
            );
        }

        $vehicle = $this->vehicles->findOrFailWithFiscal($input->vehicleId);
        $vehicleEvents = $this->vehicleEventRepo->findForVehicle($input->vehicleId)->all();

        $breakdown = $this->calculator->calculate(
            $vehicle,
            $syntheticContract !== null ? [$syntheticContract] : [],
            $vehicleEvents,
            $year,
        );

        return new FiscalPreviewData(
            fiscalYear: $year,
            daysCount: $daysCount,
            breakdown: FiscalBreakdownData::fromBreakdown($breakdown),
            supported: true,
        );
    }

    /**
     * Induced-rental preview · the non-fiscal counterpart of
     * {@see previewTaxes()}.
     *
     * Strictly equivalent to the final invoice · delegates to
     * {@see BillingBreakdownService::byContract()} (civil-month split
     * + `OptimalRateBreakdown` + `DiscountApplier`). The returned net
     * total is the one that will appear on the actual monthly invoice
     * if this contract is created and billed as-is.
     *
     * The service additionally exposes the label and rate of the
     * dominant discount (the one covering the most days over the
     * period) so the UI can show « Réductions appliquées · -15 €
     * (-3,5 %, Promo printemps 2026) ». The `RentalDiscountConflictService`
     * guarantees a single active discount per
     * `(vehicle × date)`, making the dominant value canonical.
     */
    public function previewRentals(PreviewRentalsInputData $input): RentalPreviewData
    {
        if ($input->dates === []) {
            return new RentalPreviewData(
                daysCount: 0,
                grossTotalCents: 0,
                discountCents: 0,
                netTotalCents: 0,
                appliedDiscountLabel: null,
                appliedDiscountBasisPoints: null,
                hasMissingPricing: false,
            );
        }

        $dates = $input->dates;
        sort($dates);
        $rangeStart = $dates[0];
        $rangeEnd = $dates[count($dates) - 1];

        $vehicle = $this->vehicles->findOrFailWithFiscal($input->vehicleId);

        $contract = $this->buildSyntheticContract(
            $input->vehicleId,
            $input->companyId,
            $rangeStart,
            $rangeEnd,
        );
        // Hydrate the `vehicle` relation in-memory · keeps parity
        // with the fiscal pattern (ADR-0018 defensive clip).
        $contract->setRelation('vehicle', $vehicle);

        $breakdown = $this->billingBreakdown->byContract($contract);

        if ($breakdown->hasAnyMissingPricing) {
            return new RentalPreviewData(
                daysCount: $breakdown->totalDaysUsed,
                grossTotalCents: null,
                discountCents: null,
                netTotalCents: null,
                appliedDiscountLabel: null,
                appliedDiscountBasisPoints: null,
                hasMissingPricing: true,
            );
        }

        [$label, $bp] = $this->resolveDominantDiscount(
            $contract,
            $breakdown->totalDiscountCentsPartial,
        );

        return new RentalPreviewData(
            daysCount: $breakdown->totalDaysUsed,
            grossTotalCents: $breakdown->totalGrossCentsPartial,
            discountCents: $breakdown->totalDiscountCentsPartial,
            netTotalCents: $breakdown->totalGrossCentsPartial - $breakdown->totalDiscountCentsPartial,
            appliedDiscountLabel: $label,
            appliedDiscountBasisPoints: $bp,
            hasMissingPricing: false,
            monthlyImpact: $this->buildMonthlyImpact($contract, $breakdown),
        );
    }

    /**
     * Builds the "monthly impact" of the synthetic contract on the
     * company's rental · for every civil month touched by the
     * contract, return the EXISTING monthly total (without the new
     * contract) and the NEW total (after addition, simple sum since
     * the `DateRangePicker` guarantees no overlap with an existing
     * contract).
     *
     * Implementation · one `BillingCalculator::calculateYear()` call
     * per touched year (typically 1, sometimes 2 for cross-year
     * contracts) · 2 SQL each. Active discounts are applied for
     * parity with the final invoice.
     *
     * @return list<RentalMonthlyImpactData>
     */
    private function buildMonthlyImpact(
        Contract $contract,
        ContractBillingBreakdownData $breakdown,
    ): array {
        $companyId = (int) $contract->company_id;

        $yearsTouched = [];
        foreach ($breakdown->months as $monthData) {
            $yearsTouched[$monthData->year] = true;
        }

        /** @var array<string, int> $existingByYearMonth */
        $existingByYearMonth = [];
        foreach (array_keys($yearsTouched) as $year) {
            $monthlyResults = $this->billingCalculator->calculateYear($companyId, $year);
            $index = $this->discountResolver->preloadForCompanyYear($companyId, $year);
            $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $index);

            foreach ($monthlyResults as $month => $result) {
                $existingByYearMonth[$year.'|'.$month] = $result instanceof MissingPricingException
                    ? 0
                    : $result->totalCents;
            }
        }

        $impact = [];
        foreach ($breakdown->months as $monthData) {
            if ($monthData->hasMissingPricing) {
                continue;
            }

            $existing = $existingByYearMonth[$monthData->year.'|'.$monthData->month] ?? 0;
            $induced = $monthData->totalCents ?? 0;

            $impact[] = new RentalMonthlyImpactData(
                year: $monthData->year,
                month: $monthData->month,
                existingNetCents: $existing,
                newTotalCents: $existing + $induced,
            );
        }

        return $impact;
    }

    /**
     * Resolves the DOMINANT discount (covering the most days) over a
     * synthetic contract's period. Day-by-day scan via
     * `DiscountResolver`, year by year. Short-circuit when the global
     * discount total is zero (skip the scan).
     *
     * @return array{0: ?string, 1: ?int} [label, basisPoints]
     */
    private function resolveDominantDiscount(Contract $contract, int $totalDiscountCents): array
    {
        if ($totalDiscountCents === 0) {
            return [null, null];
        }

        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        /** @var array<int, array{discount: RentalDiscount, days: int}> $byDiscount */
        $byDiscount = [];

        for ($year = $start->year; $year <= $end->year; $year++) {
            $index = $this->discountResolver->preloadForCompanyYear((int) $contract->company_id, $year);
            if ($index->isEmpty()) {
                continue;
            }

            $clipStart = $start->year < $year
                ? CarbonImmutable::create($year, 1, 1)
                : $start;
            $clipEnd = $end->year > $year
                ? CarbonImmutable::create($year, 12, 31)
                : $end;

            $cursor = $clipStart;
            while (! $cursor->isAfter($clipEnd)) {
                $discount = $index->findFor(
                    (int) $contract->vehicle_id,
                    $cursor->toDateString(),
                );
                if ($discount !== null) {
                    $id = (int) $discount->id;
                    if (! isset($byDiscount[$id])) {
                        $byDiscount[$id] = ['discount' => $discount, 'days' => 0];
                    }
                    $byDiscount[$id]['days']++;
                }
                $cursor = $cursor->addDay();
            }
        }

        if ($byDiscount === []) {
            return [null, null];
        }

        usort(
            $byDiscount,
            static fn (array $a, array $b): int => $b['days'] <=> $a['days'],
        );

        $dominant = $byDiscount[0]['discount'];

        return [$dominant->label, (int) $dominant->discount_basis_points];
    }

    /**
     * Non-persisted synthetic contract for fiscal simulation.
     */
    private function buildSyntheticContract(
        int $vehicleId,
        int $companyId,
        string $startDate,
        string $endDate,
    ): Contract {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicleId,
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'contract_reference' => null,
            'contract_type' => ContractType::Lcd->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    /**
     * Composes the list of companies present on the week with the
     * number of days each occupies.
     *
     * @param  iterable<Contract>  $weekContracts
     * @return list<WeekCompanyPresenceData>
     */
    private function buildCompaniesOnWeek(iterable $weekContracts, Carbon $start, Carbon $end): array
    {
        $byCompany = [];
        foreach ($weekContracts as $contract) {
            $companyId = $contract->company_id;
            $byCompany[$companyId] ??= [
                'company' => $contract->company,
                'days' => [],
            ];

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $iso = $cursor->toDateString();
                if ($iso >= $contract->start_date->toDateString()
                    && $iso <= $contract->end_date->toDateString()
                ) {
                    $byCompany[$companyId]['days'][$iso] = true;
                }
                $cursor->addDay();
            }
        }

        $rows = [];
        foreach ($byCompany as $entry) {
            $company = $entry['company'];
            $rows[] = new WeekCompanyPresenceData(
                company: new CompanyOptionData(
                    id: $company->id,
                    shortCode: $company->short_code,
                    legalName: $company->legal_name,
                    color: $company->color,
                ),
                days: count($entry['days']),
            );
        }

        return $rows;
    }
}
