<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use Illuminate\Support\Facades\Auth;

/**
 * Invalidates fiscal declarations whose contracts use a vehicle whose
 * fiscal characteristics changed (ADR-0015 § D8).
 *
 * Wired through `#[ObservedBy]` on the model. The detector resolves the
 * impacted declarations via
 * {@see DeclarationInvalidationDetector::flagForVfcMutation}.
 */
final class VehicleFiscalCharacteristicsObserver
{
    public function __construct(
        private readonly DeclarationInvalidationDetector $detector,
    ) {}

    /**
     * Flag declarations when a new fiscal-characteristics row is created.
     */
    public function created(VehicleFiscalCharacteristics $vfc): void
    {
        $this->detector->flagForVfcMutation(
            $vfc,
            InvalidationReasonType::VfcCreated,
            $this->actorUserId(),
        );
    }

    /**
     * Flag declarations when an existing fiscal-characteristics row is
     * updated.
     */
    public function updated(VehicleFiscalCharacteristics $vfc): void
    {
        $this->detector->flagForVfcMutation(
            $vfc,
            InvalidationReasonType::VfcUpdated,
            $this->actorUserId(),
        );
    }

    /**
     * Flag declarations when a fiscal-characteristics row is deleted.
     */
    public function deleted(VehicleFiscalCharacteristics $vfc): void
    {
        $this->detector->flagForVfcMutation(
            $vfc,
            InvalidationReasonType::VfcDeleted,
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
