<?php

declare(strict_types=1);

namespace App\Exceptions\Unavailability;

use App\Exceptions\BaseAppException;

/**
 * Tentative d'upload d'un 6ᵉ document sur une indisponibilité · la
 * limite V1 est de 5 documents par indisponibilité (cf.
 * `UploadUnavailabilityDocumentsAction`).
 */
final class TooManyUnavailabilityDocumentsException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly int $unavailabilityId,
        public readonly int $currentCount,
        public readonly int $maxAllowed,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    public static function limitReached(int $unavailabilityId, int $currentCount, int $maxAllowed): self
    {
        return new self(
            technicalMessage: sprintf(
                'Unavailability %d already has %d documents (max %d).',
                $unavailabilityId,
                $currentCount,
                $maxAllowed,
            ),
            userMessage: sprintf(
                'Limite de %d documents par indisponibilité atteinte. '
                .'Supprimez un document existant avant d\'en ajouter un nouveau.',
                $maxAllowed,
            ),
            unavailabilityId: $unavailabilityId,
            currentCount: $currentCount,
            maxAllowed: $maxAllowed,
        );
    }
}
