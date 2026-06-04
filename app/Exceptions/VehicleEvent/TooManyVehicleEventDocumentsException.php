<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleEvent;

use App\Exceptions\BaseAppException;

/**
 * Upload refused: unavailability has reached its document cap (5 per unavailability in V1).
 */
final class TooManyVehicleEventDocumentsException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly int $vehicleEventId,
        public readonly int $currentCount,
        public readonly int $maxAllowed,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    public static function limitReached(int $vehicleEventId, int $currentCount, int $maxAllowed): self
    {
        return new self(
            technicalMessage: sprintf(
                'VehicleEvent %d already has %d documents (max %d).',
                $vehicleEventId,
                $currentCount,
                $maxAllowed,
            ),
            userMessage: sprintf(
                'Limite de %d documents par indisponibilité atteinte. '
                .'Supprimez un document existant avant d\'en ajouter un nouveau.',
                $maxAllowed,
            ),
            vehicleEventId: $vehicleEventId,
            currentCount: $currentCount,
            maxAllowed: $maxAllowed,
        );
    }
}
