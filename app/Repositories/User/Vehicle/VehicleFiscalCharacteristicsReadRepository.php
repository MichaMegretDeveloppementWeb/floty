<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Fiscal\ValueObjects\VfcEffectiveSegment;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Eloquent implementation of reads on the vehicle's fiscal history.
 */
final class VehicleFiscalCharacteristicsReadRepository implements VehicleFiscalCharacteristicsReadRepositoryInterface
{
    public function findCurrentForVehicle(Vehicle $vehicle): ?VehicleFiscalCharacteristics
    {
        // When the relation is already eager-loaded, work on the
        // in-memory collection to avoid a useless extra SQL query.
        // Avoids N+1 on the fleet Index iterating over all vehicles
        // with their VFCs eager-loaded by
        // {@see VehicleReadRepository::findAllForFleetView}.
        if ($vehicle->relationLoaded('fiscalCharacteristics')) {
            return $vehicle->fiscalCharacteristics
                ->where('effective_to', null)
                ->sortByDesc('effective_from')
                ->first();
        }

        return $vehicle->fiscalCharacteristics()
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();
    }

    public function findEffectiveSegmentsForYear(Vehicle $vehicle, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        // When the COMPLETE VFC history is eager-loaded (certified by
        // `vfcHistoryComplete`, set only by
        // `VehicleReadRepository::findByIdWithFiscalHistory`), filter the
        // year segments in memory: the same vehicle's VFC is then read
        // once per page instead of once per fiscal sub-computation. The
        // flag guards against the partial `effective_to IS NULL` loads
        // (list/heatmap), whose collection would mask historical VFCs and
        // silently produce a 0 EUR computation on multi-VFC vehicles.
        if ($vehicle->vfcHistoryComplete && $vehicle->relationLoaded('fiscalCharacteristics')) {
            return $this->segmentsFromLoadedHistory($vehicle, $yearStart, $yearEnd);
        }

        // Otherwise query the DB: guarantees segmentation completeness
        // regardless of how (or whether) the relation was loaded.
        $matching = $vehicle->fiscalCharacteristics()
            ->where('effective_from', '<=', $yearEnd)
            ->where(static function ($q) use ($yearStart): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $yearStart);
            })
            ->orderBy('effective_from')
            ->get();

        return $matching
            ->map(fn (VehicleFiscalCharacteristics $vfc): VfcEffectiveSegment => $this->clampVfcToYear($vfc, $yearStart, $yearEnd))
            ->values()
            ->all();
    }

    /**
     * In-memory equivalent of the SQL filter in
     * {@see findEffectiveSegmentsForYear}, applied to the eager-loaded
     * complete `fiscalCharacteristics` collection. Same predicate
     * (`effective_from <= yearEnd AND (effective_to IS NULL OR
     * effective_to >= yearStart)`), same ascending ordering, same
     * `clampVfcToYear`, so the result is strictly equal to the SQL path.
     *
     * @return list<VfcEffectiveSegment>
     */
    private function segmentsFromLoadedHistory(
        Vehicle $vehicle,
        CarbonImmutable $yearStart,
        CarbonImmutable $yearEnd,
    ): array {
        return $vehicle->fiscalCharacteristics
            ->filter(static fn (VehicleFiscalCharacteristics $vfc): bool => $vfc->effective_from <= $yearEnd
                && ($vfc->effective_to === null || $vfc->effective_to >= $yearStart))
            ->sortBy('effective_from')
            ->map(fn (VehicleFiscalCharacteristics $vfc): VfcEffectiveSegment => $this->clampVfcToYear($vfc, $yearStart, $yearEnd))
            ->values()
            ->all();
    }

    public function findEffectiveSegmentsForYearBatch(array $vehicleIds, int $year): array
    {
        $result = array_fill_keys($vehicleIds, []);

        if ($vehicleIds === []) {
            return $result;
        }

        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        // Single SQL query for all vehicles · `IN (...)` plus
        // effective_from / effective_to bounds (PK + FK indexes are
        // sufficient for V1.0 volumes, no dedicated index needed).
        $rows = VehicleFiscalCharacteristics::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('effective_from', '<=', $yearEnd)
            ->where(static function ($q) use ($yearStart): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $yearStart);
            })
            ->orderBy('vehicle_id')
            ->orderBy('effective_from')
            ->get();

        foreach ($rows->groupBy('vehicle_id') as $vehicleId => $vfcs) {
            $result[(int) $vehicleId] = $vfcs
                ->map(fn (VehicleFiscalCharacteristics $vfc): VfcEffectiveSegment => $this->clampVfcToYear($vfc, $yearStart, $yearEnd))
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * Builds the effective segment of a VFC for a given year by
     * clipping its bounds to `[yearStart, yearEnd]`. Shared between
     * {@see findEffectiveSegmentsForYear} and
     * {@see findEffectiveSegmentsForYearBatch} to guarantee strict
     * equivalence between the two methods (batch and unitary must
     * produce the exact same `VfcEffectiveSegment` for a given
     * `vehicleId`).
     */
    private function clampVfcToYear(
        VehicleFiscalCharacteristics $vfc,
        CarbonImmutable $yearStart,
        CarbonImmutable $yearEnd,
    ): VfcEffectiveSegment {
        $start = $vfc->effective_from->toImmutable();
        $end = $vfc->effective_to !== null
            ? $vfc->effective_to->toImmutable()
            : $yearEnd;

        return new VfcEffectiveSegment(
            vfc: $vfc,
            start: $start->lessThan($yearStart) ? $yearStart : $start,
            end: $end->greaterThan($yearEnd) ? $yearEnd : $end,
        );
    }

    public function findLastVersionStrictlyBefore(
        int $vehicleId,
        DateTimeInterface $date,
    ): ?VehicleFiscalCharacteristics {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->where('effective_from', '<', $date)
            ->latest('effective_from')
            ->first();
    }

    public function findById(int $id): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::query()->findOrFail($id);
    }

    public function findAdjacent(
        VehicleFiscalCharacteristics $vfc,
        int $direction,
    ): ?VehicleFiscalCharacteristics {
        $query = VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vfc->vehicle_id)
            ->where('id', '!=', $vfc->id);

        if ($direction === -1) {
            return $query
                ->where('effective_from', '<', $vfc->effective_from)
                ->latest('effective_from')
                ->first();
        }

        return $query
            ->where('effective_from', '>', $vfc->effective_from)
            ->oldest('effective_from')
            ->first();
    }

    public function countForVehicle(int $vehicleId): int
    {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->count();
    }

    public function findOthersForVehicle(int $vehicleId, int $excludeId): array
    {
        return VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicleId)
            ->where('id', '!=', $excludeId)
            ->orderBy('effective_from')
            ->get()
            ->all();
    }
}
