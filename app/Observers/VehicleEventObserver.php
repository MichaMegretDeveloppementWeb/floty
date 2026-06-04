<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\VehicleEvent;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use Illuminate\Support\Facades\Auth;

/**
 * Invalidates fiscal declarations impacted by a reductive unavailability
 * mutation (ADR-0015 § D8).
 *
 * The observer always notifies the detector; the
 * {@see DeclarationInvalidationDetector} skips non-reductive
 * unavailabilities itself (`has_fiscal_impact = false`).
 *
 * On `updated`, the previous value of `has_fiscal_impact` is forwarded
 * so a transition from reductive to non-reductive (or vice versa) still
 * flags the existing declarations.
 */
final class VehicleEventObserver
{
    public function __construct(
        private readonly DeclarationInvalidationDetector $detector,
    ) {}

    /**
     * Flag declarations that include the unavailability period.
     */
    public function created(VehicleEvent $vehicleEvent): void
    {
        $this->detector->flagForVehicleEvent(
            $vehicleEvent,
            InvalidationReasonType::VehicleEventCreated,
            $this->actorUserId(),
        );
    }

    /**
     * Flag declarations impacted by the update, propagating the previous
     * `has_fiscal_impact` when it changed.
     */
    public function updated(VehicleEvent $vehicleEvent): void
    {
        $previousHasFiscalImpact = $vehicleEvent->wasChanged('has_fiscal_impact')
            ? (bool) $vehicleEvent->getOriginal('has_fiscal_impact')
            : null;

        $this->detector->flagForVehicleEvent(
            $vehicleEvent,
            InvalidationReasonType::VehicleEventUpdated,
            $this->actorUserId(),
            previousHasFiscalImpact: $previousHasFiscalImpact,
        );
    }

    /**
     * Flag declarations on deletion.
     */
    public function deleted(VehicleEvent $vehicleEvent): void
    {
        $this->detector->flagForVehicleEvent(
            $vehicleEvent,
            InvalidationReasonType::VehicleEventDeleted,
            $this->actorUserId(),
        );
    }

    /**
     * Resolve the acting user id, falling back to 0 in non-authenticated
     * contexts.
     */
    private function actorUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }
}
