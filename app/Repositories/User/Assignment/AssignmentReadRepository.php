<?php

declare(strict_types=1);

namespace App\Repositories\User\Assignment;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Data\User\Assignment\VehicleDatesData;
use App\DTO\Fiscal\AnnualCumulByPair;
use App\Models\Assignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des lectures Assignment.
 */
final class AssignmentReadRepository implements AssignmentReadRepositoryInterface
{
    public function loadAnnualCumul(int $year): AnnualCumulByPair
    {
        $rows = Assignment::query()
            ->whereYear('date', $year)
            ->select('vehicle_id', 'company_id', DB::raw('COUNT(*) as days'))
            ->groupBy('vehicle_id', 'company_id')
            ->get();

        $byPair = [];
        foreach ($rows as $row) {
            $byPair[$row->vehicle_id.'|'.$row->company_id] = (int) $row->days;
        }

        return new AnnualCumulByPair($byPair);
    }

    public function loadWeekDensity(int $year): array
    {
        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->get(['vehicle_id', 'date']);

        $density = [];
        foreach ($assignments as $a) {
            $week = (int) Carbon::parse($a->date)->isoWeek;
            $key = $a->vehicle_id.'|'.$week;
            $density[$key] = ($density[$key] ?? 0) + 1;
        }

        return $density;
    }

    public function findVehicleDates(int $vehicleId, int $year): VehicleDatesData
    {
        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->get(['company_id', 'date']);

        $busy = [];
        $byCompany = [];
        foreach ($assignments as $a) {
            $iso = $a->date->toDateString();
            $busy[] = $iso;
            $byCompany[(string) $a->company_id] ??= [];
            $byCompany[(string) $a->company_id][] = $iso;
        }

        return new VehicleDatesData(
            vehicleBusyDates: array_values(array_unique($busy)),
            pairDates: $byCompany,
        );
    }

    public function findWeekAssignments(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        return Assignment::query()
            ->with('company:id,short_code,legal_name,color')
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    public function findDatesForPair(int $vehicleId, int $companyId, int $year): array
    {
        return Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->where('company_id', $companyId)
            ->pluck('date')
            ->map(static fn ($d): string => Carbon::parse($d)->toDateString())
            ->all();
    }

    public function countForYear(int $year): int
    {
        return Assignment::query()->whereYear('date', $year)->count();
    }
}
