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

        // Pas d'optimisation `relationLoaded('fiscalCharacteristics')`
        // ici : les eager-loads VFC du projet sont quasi tous restreints
        // à `effective_to IS NULL` (cf. `findOrFailWithFiscal`,
        // `findAllForFleetView`, `findByIdsIndexed`, `findAllForHeatmap`).
        // Filtrer en mémoire sur cette collection partielle masquerait
        // toutes les VFC historiques et produirait silencieusement un
        // calcul fiscal à 0 € sur les véhicules multi-VFC. Toujours
        // interroger la base garantit la complétude de la segmentation.
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

    public function findEffectiveSegmentsForYearBatch(array $vehicleIds, int $year): array
    {
        $result = array_fill_keys($vehicleIds, []);

        if ($vehicleIds === []) {
            return $result;
        }

        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        // Une seule query SQL pour tous les véhicules · le `IN (...)` +
        // les bornes effective_from / effective_to sont indexées (les
        // PK + FK suffisent · pas d'index spécifique nécessaire pour
        // un volume V1.0 typique).
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
     * Helper · construit le segment effectif d'une VFC pour une année
     * donnée en clippant ses bornes à `[yearStart, yearEnd]`.
     * Mutualisé entre {@see findEffectiveSegmentsForYear} et
     * {@see findEffectiveSegmentsForYearBatch} pour garantir
     * l'équivalence stricte des deux méthodes (cf. doctrine
     * `optimisations-conditionnelles.md` · le batch et l'unitaire
     * doivent produire exactement le même `VfcEffectiveSegment` pour
     * un même `vehicleId`).
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
