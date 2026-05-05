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
 * Implémentation Eloquent des lectures sur l'historique fiscal d'un
 * véhicule.
 */
final class VehicleFiscalCharacteristicsReadRepository implements VehicleFiscalCharacteristicsReadRepositoryInterface
{
    public function findCurrentForVehicle(Vehicle $vehicle): ?VehicleFiscalCharacteristics
    {
        // Si la relation est préchargée (eager load via `with(...)`),
        // on travaille sur la collection en mémoire pour éviter une
        // nouvelle requête SQL inutile. Évite le N+1 sur l'Index Flotte
        // qui itère sur tous les véhicules avec leurs VFC déjà
        // eager-loadées par {@see VehicleReadRepository::findAllForFleetView}.
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

        if ($vehicle->relationLoaded('fiscalCharacteristics')) {
            $matching = $vehicle->fiscalCharacteristics
                ->filter(static fn (VehicleFiscalCharacteristics $vfc): bool => $vfc->effective_from->lessThanOrEqualTo($yearEnd)
                    && ($vfc->effective_to === null || $vfc->effective_to->greaterThanOrEqualTo($yearStart)))
                ->sortBy(static fn (VehicleFiscalCharacteristics $vfc): string => $vfc->effective_from->toDateString())
                ->values();
        } else {
            $matching = $vehicle->fiscalCharacteristics()
                ->where('effective_from', '<=', $yearEnd)
                ->where(static function ($q) use ($yearStart): void {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $yearStart);
                })
                ->orderBy('effective_from')
                ->get();
        }

        return $matching
            ->map(static function (VehicleFiscalCharacteristics $vfc) use ($yearStart, $yearEnd): VfcEffectiveSegment {
                $start = CarbonImmutable::parse($vfc->effective_from->toDateString());
                $end = $vfc->effective_to !== null
                    ? CarbonImmutable::parse($vfc->effective_to->toDateString())
                    : $yearEnd;

                return new VfcEffectiveSegment(
                    vfc: $vfc,
                    start: $start->lessThan($yearStart) ? $yearStart : $start,
                    end: $end->greaterThan($yearEnd) ? $yearEnd : $end,
                );
            })
            ->values()
            ->all();
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
