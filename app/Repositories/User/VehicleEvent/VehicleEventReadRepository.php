<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Models\VehicleEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class VehicleEventReadRepository implements VehicleEventReadRepositoryInterface
{
    public function findForVehicle(int $vehicleId): Collection
    {
        return VehicleEvent::query()
            ->with('documents')
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function findForVehicleIds(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $rows = VehicleEvent::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->orderByDesc('start_date')
            ->get();

        $byVehicle = [];
        foreach ($rows as $vehicleEvent) {
            $byVehicle[$vehicleEvent->vehicle_id] ??= [];
            $byVehicle[$vehicleEvent->vehicle_id][] = $vehicleEvent;
        }

        return $byVehicle;
    }

    public function findById(int $id): VehicleEvent
    {
        return VehicleEvent::query()->findOrFail($id);
    }

    public function findActiveOverlappingDateForVehicle(int $vehicleId, string $date): Collection
    {
        return VehicleEvent::query()
            ->where('vehicle_id', $vehicleId)
            ->where(function ($q) use ($date): void {
                $q->whereNull('end_date')->orWhere('end_date', '>', $date);
            })
            ->orderBy('start_date')
            ->get();
    }

    public function findUnavailableDaysByWeekForVehicle(int $vehicleId, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $rows = VehicleEvent::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $yearEnd)
            ->where(function ($q) use ($yearStart): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $yearStart);
            })
            ->get(['start_date', 'end_date']);

        // [weekNumber => Set<dayKey>] · Set deduplicates if two
        // unavailabilities overlap the same day (rare).
        /** @var array<int, array<string, bool>> $byWeekDays */
        $byWeekDays = [];
        foreach ($rows as $row) {
            $start = $row->start_date->greaterThan($yearStart) ? $row->start_date : $yearStart;
            $end = $row->end_date === null || $row->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $row->end_date;

            // Explicit reassignment · `start_date`/`end_date` are cast
            // to CarbonImmutable (cf. AppServiceProvider::Date::use),
            // so `addDay()` does not mutate the instance in place.
            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $week = (int) $cursor->isoWeek;
                    $byWeekDays[$week] ??= [];
                    $byWeekDays[$week][$cursor->toDateString()] = true;
                }
                $cursor = $cursor->addDay();
            }
        }

        $byWeek = [];
        foreach ($byWeekDays as $week => $days) {
            $byWeek[$week] = count($days);
        }
        ksort($byWeek);

        return $byWeek;
    }
}
