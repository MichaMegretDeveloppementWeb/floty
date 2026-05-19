<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Data\User\Vehicle\VehicleExitImpactData;
use App\Exceptions\BaseAppException;

/**
 * Vehicle exit blocked because at least one contract or unavailability overflows the proposed exit date (ADR-0018 D7).
 * The user must resolve conflicts manually; backend safety net against out-of-UI POSTs and race conditions.
 */
final class VehicleExitBlockedByConflictsException extends BaseAppException
{
    private VehicleExitImpactData $impact;

    public static function withImpact(VehicleExitImpactData $impact): self
    {
        $contractsCount = count($impact->conflictingContracts);
        $unavailabilitiesCount = count($impact->conflictingUnavailabilities);
        $total = $contractsCount + $unavailabilitiesCount;

        $userMessage = sprintf(
            'Impossible de retirer ce véhicule : %d élément(s) actif(s) débordent la date proposée (%d location(s), %d indisponibilité(s)). Veuillez les résoudre avant de retirer le véhicule.',
            $total,
            $contractsCount,
            $unavailabilitiesCount,
        );

        $exception = new self(
            technicalMessage: sprintf(
                'Vehicle exit blocked by %d conflicting elements (%d contracts, %d unavailabilities)',
                $total,
                $contractsCount,
                $unavailabilitiesCount,
            ),
            userMessage: $userMessage,
        );

        $exception->impact = $impact;

        return $exception;
    }

    public function impact(): VehicleExitImpactData
    {
        return $this->impact;
    }
}
