<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use Illuminate\Support\Facades\Auth;

/**
 * Observer VFC · invalidation des déclarations fiscales (Phase 11 D3,
 * ADR-0015 § D8). Flag les déclarations dont au moins un contrat utilise
 * ce véhicule sur l'année. Périmètre dérivé par
 * {@see DeclarationInvalidationDetector::flagForVfcMutation}. Distingue
 * `created` / `updated` / `deleted` pour le type d'`InvalidationReasonType`.
 *
 * Branchement via attribut `#[ObservedBy]` sur le modèle.
 */
final class VehicleFiscalCharacteristicsObserver
{
    public function __construct(
        private readonly DeclarationInvalidationDetector $detector,
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
    }

    private function actorUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }
}
