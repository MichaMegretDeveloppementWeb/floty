<?php

declare(strict_types=1);

namespace App\Exceptions\Contract;

use App\Exceptions\BaseAppException;

/**
 * Tentative d'upload d'un 6ᵉ document sur un contrat - la limite V1 est
 * de 5 documents par contrat (cf. `UploadContractDocumentAction`).
 */
final class TooManyContractDocumentsException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly int $contractId,
        public readonly int $currentCount,
        public readonly int $maxAllowed,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    public static function limitReached(int $contractId, int $currentCount, int $maxAllowed): self
    {
        return new self(
            technicalMessage: sprintf(
                'Contract %d already has %d documents (max %d).',
                $contractId,
                $currentCount,
                $maxAllowed,
            ),
            userMessage: sprintf(
                'Limite de %d documents par contrat atteinte. '
                .'Supprimez un document existant avant d\'en ajouter un nouveau.',
                $maxAllowed,
            ),
            contractId: $contractId,
            currentCount: $currentCount,
            maxAllowed: $maxAllowed,
        );
    }
}
