<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\VehicleEvent\VehicleEventIndexQueryData;
use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;
use App\Models\VehicleEventCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VehicleEventReadRepository implements VehicleEventReadRepositoryInterface
{
    public function findForVehicle(int $vehicleId): Collection
    {
        return VehicleEvent::query()
            ->with(['documents', 'categories'])
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function paginateForIndex(VehicleEventIndexQueryData $query): LengthAwarePaginator
    {
        $eloquent = VehicleEvent::query()
            ->with(['vehicle:id,license_plate', 'categories']);

        $this->applyIndexFilters($eloquent, $query);

        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        match ($query->sortKey) {
            'vehicle' => $eloquent
                ->leftJoin('vehicles', 'vehicles.id', '=', 'vehicle_events.vehicle_id')
                ->orderBy('vehicles.license_plate', $direction)
                ->select('vehicle_events.*'),
            'type' => $eloquent->orderBy('type', $direction),
            'amount' => $eloquent->orderBy('amount_cents', $direction),
            'startDate' => $eloquent->orderBy('start_date', $direction),
            default => $eloquent->orderByDesc('start_date'),
        };

        return $eloquent->paginate(perPage: $query->perPage, page: $query->page);
    }

    public function sumAmountForIndex(VehicleEventIndexQueryData $query): int
    {
        $eloquent = VehicleEvent::query();
        $this->applyIndexFilters($eloquent, $query);

        return (int) $eloquent->sum('amount_cents');
    }

    /**
     * Distinct values for the « Type » filter axis, mirroring the categories
     * behaviour for custom events: the known enum types actually present
     * (excluding the generic « other ») plus the distinct custom titles of the
     * « other » events (the values the user typed, « la saisie fait foi »).
     * The generic « Personnalisé » bucket is never offered as a suggestion.
     *
     * @return list<string>
     */
    public function distinctTypesPresent(): array
    {
        $known = VehicleEvent::query()
            ->select('type')
            ->distinct()
            ->where('type', '!=', VehicleEventType::Other->value)
            ->orderBy('type')
            ->pluck('type')
            ->map(static fn (VehicleEventType $type): string => $type->value)
            ->all();

        $customTitles = VehicleEvent::query()
            ->select('title')
            ->distinct()
            ->where('type', VehicleEventType::Other->value)
            // System lifecycle markers (acquisition / fleet exit) are stored as
            // `other` with a system_kind; they belong to the « Cycle de vie »
            // category, not the user's custom types, so they never seed the axis.
            ->whereNull('system_kind')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->orderBy('title')
            ->pluck('title')
            ->all();

        return [...$known, ...$customTitles];
    }

    public function distinctEventYears(): array
    {
        return VehicleEvent::query()
            ->selectRaw('DISTINCT YEAR(start_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(static fn (mixed $year): int => (int) $year)
            ->all();
    }

    public function existsAnyVehicleEvent(): bool
    {
        return VehicleEvent::query()->exists();
    }

    /**
     * Shared filter clauses for the global index (used by both the paginated
     * listing and the cost total). Multi-value axes are OR-within / AND-between.
     *
     * @param  Builder<VehicleEvent>  $query
     */
    private function applyIndexFilters(Builder $query, VehicleEventIndexQueryData $data): void
    {
        if ($data->types !== null && $data->types !== []) {
            $enumValues = array_map(
                static fn (VehicleEventType $t): string => $t->value,
                VehicleEventType::cases(),
            );

            // A selected value is either a known enum type, or a custom title of
            // an « other » event (which surfaces by its title, not under the
            // generic « Personnalisé », cf. distinctTypesPresent()).
            $knownTypes = array_values(array_filter(
                $data->types,
                static fn (string $t): bool => in_array($t, $enumValues, true)
                    && $t !== VehicleEventType::Other->value,
            ));
            $customTitles = array_values(array_filter(
                $data->types,
                static fn (string $t): bool => ! in_array($t, $enumValues, true),
            ));

            if ($knownTypes !== [] || $customTitles !== []) {
                $query->where(static function (Builder $w) use ($knownTypes, $customTitles): void {
                    if ($knownTypes !== []) {
                        $w->orWhereIn('type', $knownTypes);
                    }

                    if ($customTitles !== []) {
                        $w->orWhere(static function (Builder $o) use ($customTitles): void {
                            $o->where('type', VehicleEventType::Other->value)
                                ->whereIn('title', $customTitles);
                        });
                    }
                });
            }
        }

        if ($data->categories !== null && $data->categories !== []) {
            $lowered = array_map(
                static fn (string $c): string => mb_strtolower(trim($c)),
                $data->categories,
            );

            $query->whereHas('categories', static function (Builder $c) use ($lowered): void {
                $c->whereIn(DB::raw('LOWER(category)'), $lowered);
            });
        }

        if ($data->year !== null) {
            $query->whereBetween('start_date', [
                sprintf('%d-01-01', $data->year),
                sprintf('%d-12-31', $data->year),
            ]);
        }

        if ($data->search !== null) {
            $term = '%'.$data->search.'%';

            $query->where(static function (Builder $w) use ($term): void {
                $w->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('vehicle', static fn (Builder $v) => $v->where('license_plate', 'like', $term));
            });
        }
    }

    public function distinctCategories(): array
    {
        return VehicleEventCategory::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
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

    public function findSystemEventForVehicle(int $vehicleId, VehicleEventSystemKind $kind): ?VehicleEvent
    {
        return VehicleEvent::query()
            ->where('vehicle_id', $vehicleId)
            ->where('system_kind', $kind)
            ->first();
    }

    public function findForVehicleDetail(int $vehicleId, int $vehicleEventId): VehicleEvent
    {
        return VehicleEvent::query()
            ->with(['documents', 'categories'])
            ->where('vehicle_id', $vehicleId)
            ->findOrFail($vehicleEventId);
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
