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

    public function findOverlappingWeeksForVehicle(int $vehicleId, int $year): array
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

        $weeks = [];
        foreach ($rows as $row) {
            $start = $row->start_date->greaterThan($yearStart) ? $row->start_date : $yearStart;
            $end = $row->end_date === null || $row->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $row->end_date;

            $cursor = $start->copy();
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $weeks[(int) $cursor->isoWeek] = true;
                }
                $cursor->addDay();
            }
        }

        $list = array_keys($weeks);
        sort($list);

        return $list;
    }
}
