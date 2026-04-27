<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Fiscal\FiscalBreakdownData;
use App\Data\User\Fiscal\FiscalPreviewData;
use App\Data\User\Planning\PlanningWeekData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekCompanyPresenceData;
use App\Data\User\Planning\WeekDayAssignmentData;
use App\Data\User\Planning\WeekDaySlotData;
use App\Models\Assignment;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Support\Carbon;

/**
 * Détail d'une semaine pour le drawer planning + preview des taxes
 * induites par une nouvelle attribution.
 */
final class WeekDetailService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly AssignmentReadRepositoryInterface $assignments,
        private readonly FiscalCalculator $calculator,
    ) {}

    /**
     * Construit le payload du drawer pour une semaine donnée d'un véhicule.
     */
    public function buildWeek(int $vehicleId, int $weekNumber, int $year): PlanningWeekData
    {
        $vehicle = $this->vehicles->findOrFailWithFiscal($vehicleId);

        $start = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $dayAssignments = $this->assignments
            ->findWeekAssignments($vehicleId, $start, $end)
            ->keyBy(static fn (Assignment $a): string => Carbon::parse($a->date)->toDateString());

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $iso = $cursor->toDateString();
            $assignment = $dayAssignments->get($iso);
            $days[] = new WeekDaySlotData(
                date: $iso,
                dayLabel: $cursor->translatedFormat('D d'),
                assignment: $assignment !== null
                    ? new WeekDayAssignmentData(
                        id: $assignment->id,
                        company: new CompanyOptionData(
                            id: $assignment->company->id,
                            shortCode: $assignment->company->short_code,
                            legalName: $assignment->company->legal_name,
                            color: $assignment->company->color,
                        ),
                    )
                    : null,
            );
            $cursor->addDay();
        }

        $companiesOnWeek = $dayAssignments->groupBy('company_id')
            ->map(static fn ($group, $companyId): WeekCompanyPresenceData => new WeekCompanyPresenceData(
                company: new CompanyOptionData(
                    id: (int) $companyId,
                    shortCode: $group->first()->company->short_code,
                    legalName: $group->first()->company->legal_name,
                    color: $group->first()->company->color,
                ),
                days: $group->count(),
            ))
            ->values()
            ->all();

        return new PlanningWeekData(
            weekNumber: $weekNumber,
            weekStart: $start->toDateString(),
            weekEnd: $end->toDateString(),
            vehicleId: $vehicle->id,
            licensePlate: $vehicle->license_plate,
            days: $days,
            companiesOnWeek: $companiesOnWeek,
        );
    }

    /**
     * Aperçu fiscal des taxes induites par l'ajout de N dates pour
     * un couple (véhicule, entreprise).
     */
    public function previewTaxes(PreviewTaxesInputData $input, int $year): FiscalPreviewData
    {
        $yearPrefix = $year.'-';

        $newDates = array_values(array_filter(
            $input->dates,
            static fn (string $d): bool => str_starts_with($d, $yearPrefix),
        ));

        $alreadyAssignedForPair = $this->assignments->findDatesForPair(
            $input->vehicleId,
            $input->companyId,
            $year,
        );

        $newForPair = array_values(array_diff($newDates, $alreadyAssignedForPair));
        $newDaysCount = count($newForPair);

        $existingCumul = count($alreadyAssignedForPair);
        $futureCumul = $existingCumul + $newDaysCount;

        $vehicle = $this->vehicles->findOrFailWithFiscal($input->vehicleId);

        $before = $existingCumul > 0
            ? $this->calculator->calculate($vehicle, $existingCumul, $existingCumul, $year)
            : null;
        $after = $this->calculator->calculate($vehicle, $futureCumul, $futureCumul, $year);

        $incrementalDue = $after->totalDue - ($before?->totalDue ?? 0.0);

        return new FiscalPreviewData(
            fiscalYear: $year,
            newDaysCount: $newDaysCount,
            existingCumul: $existingCumul,
            futureCumul: $futureCumul,
            before: $before !== null ? FiscalBreakdownData::fromBreakdown($before) : null,
            after: FiscalBreakdownData::fromBreakdown($after),
            incrementalDue: round($incrementalDue, 2),
        );
    }
}
