<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Vehicle;
use App\Services\Billing\BillingCalculator;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Reacts to {@see Vehicle} mutations that change the billable or
 * declarable scope. Currently the only watched field is `exit_date`:
 *   - invoices that include the vehicle are flagged divergent because
 *     {@see BillingCalculator} clips usage to
 *     `exit_date`;
 *   - fiscal declarations covering the vehicle are flagged obsolete
 *     because `exit_date` closes the contracts and changes the taxable
 *     scope.
 *
 * Wired through `#[ObservedBy]` on the model.
 */
final class VehicleObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationInvalidator,
    ) {}

    /**
     * Flag invoices and declarations when `exit_date` is added, moved or
     * cleared.
     */
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
