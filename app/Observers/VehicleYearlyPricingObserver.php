<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VehicleYearlyPricing;
use App\Services\Invoice\InvoiceDivergenceFlagger;

/**
 * Marque divergentes les factures impactées par un changement de tarif
 * véhicule × année (T6 / Phase 14.R).
 *
 * Toute mutation d'un `VehicleYearlyPricing` (création, modification,
 * suppression) recalcule potentiellement le total des factures déjà
 * émises pour cette année × ce véhicule × les entreprises clientes
 * concernées. Le {@see InvoiceDivergenceFlagger} fait le tri en SQL.
 *
 * **Branchement** : `#[ObservedBy([VehicleYearlyPricingObserver::class])]`
 * sur le Model.
 */
final class VehicleYearlyPricingObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
    ) {}

    public function created(VehicleYearlyPricing $pricing): void
    {
        $this->flagger->flagForVehiclePricingYear($pricing->vehicle_id, $pricing->year);
    }

    public function updated(VehicleYearlyPricing $pricing): void
    {
        $this->flagger->flagForVehiclePricingYear($pricing->vehicle_id, $pricing->year);
    }

    public function deleted(VehicleYearlyPricing $pricing): void
    {
        $this->flagger->flagForVehiclePricingYear($pricing->vehicle_id, $pricing->year);
    }
}
