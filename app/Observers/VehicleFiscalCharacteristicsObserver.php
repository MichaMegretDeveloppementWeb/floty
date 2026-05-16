<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Fiscal\FiscalCacheInvalidator;
use Illuminate\Support\Facades\Auth;

/**
 * Observer VFC · 2 responsabilités fiscales ·
 *
 *   1. **Invalidation déclarations** (Phase 11 D3, ADR-0015 § D8) ·
 *      flag les déclarations fiscales dont au moins un contrat utilise
 *      ce véhicule sur l'année. Périmètre dérivé par
 *      {@see DeclarationInvalidationDetector::flagForVfcMutation}.
 *      Distingue `created` / `updated` / `deleted` pour le type
 *      d'`InvalidationReasonType`.
 *
 *   2. **Invalidation cache fiscal** (chantier perf 2026-05-17) ·
 *      `vehicleFullYearTaxBreakdown` mis en cache via
 *      {@see FiscalCacheInvalidator} · toute mutation VFC purge les
 *      clés pour ce véhicule sur la plage d'années supportées. Utilise
 *      `saved` (couvre create + update) et `deleted` pour minimiser
 *      la duplication d'appels (saved + created + updated se
 *      déclenchent tous 3 sur ::create() · 3 invalidations idempotentes,
 *      gaspilleuses · saved seul suffit).
 *
 * **NON couvert ici** (couvert manuellement dans le repo) ·
 *   - `query()->where()->delete()` bulk dans
 *     `VehicleFiscalCharacteristicsWriteRepository::deleteOne` +
 *     `deleteVersionsFromDate` · n'invoquent PAS l'event `deleted`.
 *     Le repo appelle directement
 *     {@see FiscalCacheInvalidator::invalidateForVehicle()} AVANT le
 *     bulk delete.
 *
 * Branchement via attribut `#[ObservedBy]` sur le modèle.
 */
final class VehicleFiscalCharacteristicsObserver
{
    public function __construct(
        private readonly DeclarationInvalidationDetector $detector,
        private readonly FiscalCacheInvalidator $cacheInvalidator,
    ) {}

    public function created(VehicleFiscalCharacteristics $vfc): void
    {
        $this->detector->flagForVfcMutation(
            $vfc,
            InvalidationReasonType::VfcCreated,
            $this->actorUserId(),
        );
    }

    public function updated(VehicleFiscalCharacteristics $vfc): void
    {
        $this->detector->flagForVfcMutation(
            $vfc,
            InvalidationReasonType::VfcUpdated,
            $this->actorUserId(),
        );
    }

    public function deleted(VehicleFiscalCharacteristics $vfc): void
    {
        $this->detector->flagForVfcMutation(
            $vfc,
            InvalidationReasonType::VfcDeleted,
            $this->actorUserId(),
        );

        // Cache fiscal · couvert ici car `saved` ne fire pas sur delete.
        $this->cacheInvalidator->invalidateForVehicle($vfc->vehicle_id);
    }

    /**
     * Cache fiscal · couvre create + update via 1 seul hook (évite la
     * double invalidation `created+saved` ou `updated+saved`).
     */
    public function saved(VehicleFiscalCharacteristics $vfc): void
    {
        $this->cacheInvalidator->invalidateForVehicle($vfc->vehicle_id);
    }

    private function actorUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }
}
