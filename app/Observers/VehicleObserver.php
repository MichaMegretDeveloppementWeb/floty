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
 * Reacts to {@see Vehicle} mutations: an `exit_date` change flags the dependent
 * invoices ({@see BillingCalculator}) and fiscal declarations; a change to a
 * control anchor or `exit_date`, and vehicle creation, recompute the
 * `controls_due_from` cache.
 */
final class VehicleObserver
{
    /** Vehicle date columns feeding a control's next due date. */
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
