<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use Illuminate\Support\Facades\Auth;

/**
 * Invalide les déclarations fiscales impactées par une mutation de
 * VFC (Phase 11 D3, ADR-0015 § D8).
 *
 * Une VFC change les caractéristiques fiscales d'un véhicule (puissance,
 * énergie, CO2…) : toute déclaration dont au moins un contrat utilise
 * ce véhicule sur l'année est impactée. Périmètre dérivé par
 * {@see DeclarationInvalidationDetector::flagForVfcMutation}.
 *
 * Branchement via attribut `#[ObservedBy]` sur le modèle (pattern
 * cohérent avec `ContractObserver` / `VehicleObserver`).
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
