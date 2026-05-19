<?php

declare(strict_types=1);

namespace App\Exceptions\Contract;

use App\Exceptions\BaseAppException;

/**
 * Upload refused: contract has reached its document cap (5 per contract in V1).
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
                'Limite de %d documents par location atteinte. '
                .'Supprimez un document existant avant d\'en ajouter un nouveau.',
                $maxAllowed,
            ),
            contractId: $contractId,
            currentCount: $currentCount,
            maxAllowed: $maxAllowed,
        );
    }
}
