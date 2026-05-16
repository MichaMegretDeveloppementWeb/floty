<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Vehicle;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Fiscal\FiscalCacheInvalidator;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Observer Vehicle · 3 responsabilités sur mutations du modèle ·
 *
 *   1. **Flag divergence factures** (T5 ADR-0018) · sur changement
 *      d'`exit_date`, {@see InvoiceDivergenceFlagger::flagForVehicle}
 *      car `BillingCalculator` clip à `exit_date`.
 *
 *   2. **Invalidation déclarations fiscales** (audit pré-livraison
 *      D5.7.8) · sur changement d'`exit_date`,
 *      {@see DeclarationInvalidationDetector::flagForVehicle} car
 *      `exit_date` clôture les contrats et change le périmètre taxable.
 *
 *   3. **Invalidation cache fiscal** (chantier perf 2026-05-17) ·
 *      `vehicleFullYearTaxBreakdown` cache via
 *      {@see FiscalCacheInvalidator}. Le SEUL champ Vehicle lu par le
 *      pipeline est `first_origin_registration_date` (R-2024-017
 *      hybride rechargeable). Couvre aussi le cycle de vie (delete /
 *      restore / forceDelete) par cohérence.
 *
 * **Pourquoi `exit_date` ne déclenche PAS l'invalidation cache** ·
 * `vehicleFullYearTaxBreakdown` calcule la taxe pleine THÉORIQUE
 * (100 % usage sur l'année) · indépendante d'`exit_date` qui n'impacte
 * que la taxe DUE réelle (`vehicleAnnualTax`, non cachée).
 *
 * **Création de véhicule** · `saved` fire au `created` mais
 * `wasChanged('first_origin_registration_date')` retourne true à la
 * création si le champ a été set · invalidation idempotente sur cache
 * vide = no-op.
 *
 * **Branchement** · `#[ObservedBy([VehicleObserver::class])]` sur le
 * Model.
 */
final class VehicleObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationInvalidator,
        private readonly FiscalCacheInvalidator $cacheInvalidator,
    ) {}

    public function updated(Vehicle $vehicle): void
    {
        if (! $vehicle->wasChanged('exit_date')) {
            return;
        }

        $this->flagger->flagForVehicle($vehicle->id);

        $this->declarationInvalidator->flagForVehicle(
            vehicle: $vehicle,
            type: InvalidationReasonType::VehicleUpdated,
            actorUserId: Auth::id() ?? 0,
            fieldsChanged: ['exit_date'],
        );
    }

    /**
     * Cache fiscal · couvre create + update via 1 seul hook,
     * conditionnel sur le seul champ Vehicle qui impacte le pipeline.
     */
    public function saved(Vehicle $vehicle): void
    {
        if ($vehicle->wasChanged('first_origin_registration_date')) {
            $this->cacheInvalidator->invalidateForVehicle($vehicle->id);
        }
    }

    public function deleted(Vehicle $vehicle): void
    {
        $this->cacheInvalidator->invalidateForVehicle($vehicle->id);
    }

    public function restored(Vehicle $vehicle): void
    {
        $this->cacheInvalidator->invalidateForVehicle($vehicle->id);
    }

    public function forceDeleted(Vehicle $vehicle): void
    {
        $this->cacheInvalidator->invalidateForVehicle($vehicle->id);
    }
}
