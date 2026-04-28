<?php

declare(strict_types=1);

namespace App\Repositories\User\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Models\Unavailability;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class UnavailabilityReadRepository implements UnavailabilityReadRepositoryInterface
{
    public function findForVehicle(int $vehicleId): Collection
    {
        return Unavailability::query()
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function findById(int $id): Unavailability
    {
        return Unavailability::query()->findOrFail($id);
    }

    public function findUnavailableDaysByWeekForVehicle(int $vehicleId, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $rows = Unavailability::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $yearEnd)
            ->where(function ($q) use ($yearStart): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $yearStart);
            })
            ->get(['start_date', 'end_date']);

        // [weekNumber => Set<dayKey>] — Set pour dédupliquer si deux
        // indispos chevauchent la même journée (cas exceptionnel).
        /** @var array<int, array<string, bool>> $byWeekDays */
        $byWeekDays = [];
        foreach ($rows as $row) {
            $start = $row->start_date->greaterThan($yearStart) ? $row->start_date : $yearStart;
            $end = $row->end_date === null || $row->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $row->end_date;

            // Réassignation explicite — `start_date`/`end_date` sont castés
            // en CarbonImmutable (cf. AppServiceProvider::Date::use), donc
            // `addDay()` ne mute pas l'instance en place.
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
