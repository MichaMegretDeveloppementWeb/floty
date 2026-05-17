<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Vehicle;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Observer Vehicle · 2 responsabilités sur mutations du modèle ·
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
 * **Branchement** · `#[ObservedBy([VehicleObserver::class])]` sur le
 * Model.
 */
final class VehicleObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationInvalidator,
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
}
