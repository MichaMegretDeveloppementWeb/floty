<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Unavailability;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use Illuminate\Support\Facades\Auth;

/**
 * Invalide les déclarations fiscales impactées par une mutation
 * d'indispo réductrice (Phase 11 D3, ADR-0015 § D8).
 *
 * **Court-circuit** : le {@see DeclarationInvalidationDetector} skippe
 * lui-même les indispos non réductrices (`has_fiscal_impact = false`).
 * L'observer envoie systématiquement, le service décide.
 *
 * Cas particulier `updated` : si l'utilisateur change le `type` d'une
 * indispo (réductrice → non-réductrice), il faut quand même flag les
 * déclarations existantes (le state ancien était impactant).
 */
final class UnavailabilityObserver
{
    public function __construct(
        private readonly DeclarationInvalidationDetector $detector,
    ) {}

    public function created(Unavailability $unavailability): void
    {
        $this->detector->flagForUnavailability(
            $unavailability,
            InvalidationReasonType::UnavailabilityCreated,
            $this->actorUserId(),
        );
    }

    public function updated(Unavailability $unavailability): void
    {
        $previousHasFiscalImpact = $unavailability->wasChanged('has_fiscal_impact')
            ? (bool) $unavailability->getOriginal('has_fiscal_impact')
            : null;

        $this->detector->flagForUnavailability(
            $unavailability,
            InvalidationReasonType::UnavailabilityUpdated,
            $this->actorUserId(),
            previousHasFiscalImpact: $previousHasFiscalImpact,
        );
    }

    public function deleted(Unavailability $unavailability): void
    {
        $this->detector->flagForUnavailability(
            $unavailability,
            InvalidationReasonType::UnavailabilityDeleted,
            $this->actorUserId(),
        );
    }

    private function actorUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }
}
