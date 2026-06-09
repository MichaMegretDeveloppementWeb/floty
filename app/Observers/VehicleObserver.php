<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Vehicle;
use App\Services\Billing\BillingCalculator;
use App\Services\Control\ControlDueDateRecomputeService;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Reacts to {@see Vehicle} mutations:
 *   - `exit_date` changes flag the dependent invoices (usage clipped by
 *     {@see BillingCalculator}) and fiscal declarations (taxable scope) ;
 *   - changes to a control anchor date or `exit_date`, and vehicle creation,
 *     recompute the materialised `controls_due_from` cache (anchors feed the
 *     next due date, exit gates which controls count). The recompute writes
 *     the derived column via the query builder (no model event) so it does not
 *     recurse here.
 *
 * Wired through `#[ObservedBy]` on the model.
 */
final class VehicleObserver
{
    /**
     * Vehicle date columns that feed a control's next due date. A change to any
     * of them (or to `exit_date`) invalidates the materialised due-from cache.
     */
    private const array CONTROL_ANCHOR_COLUMNS = [
        'first_french_registration_date',
        'first_origin_registration_date',
        'first_economic_use_date',
        'acquisition_date',
    ];

    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationInvalidator,
        private readonly ControlDueDateRecomputeService $controlDueDates,
    ) {}

    public function created(Vehicle $vehicle): void
    {
        $this->controlDueDates->forVehicleId($vehicle->id);
    }

    /**
     * Flag invoices/declarations on `exit_date` change; recompute the control
     * due-from cache when an anchor date or `exit_date` changes.
     */
    public function updated(Vehicle $vehicle): void
    {
        if ($vehicle->wasChanged('exit_date')) {
            $this->flagger->flagForVehicle($vehicle->id);

            $this->declarationInvalidator->flagForVehicle(
                vehicle: $vehicle,
                type: InvalidationReasonType::VehicleUpdated,
                actorUserId: Auth::id() ?? 0,
                fieldsChanged: ['exit_date'],
            );
        }

        if ($vehicle->wasChanged([...self::CONTROL_ANCHOR_COLUMNS, 'exit_date'])) {
            $this->controlDueDates->forVehicleId($vehicle->id);
        }
    }
}
