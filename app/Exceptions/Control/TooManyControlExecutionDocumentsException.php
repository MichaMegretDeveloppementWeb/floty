<?php

declare(strict_types=1);

namespace App\Exceptions\Control;

use App\Exceptions\BaseAppException;

/**
 * Upload refused: a control execution has reached its document cap (5 in V1).
 */
final class TooManyControlExecutionDocumentsException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly int $controlExecutionId,
        public readonly int $currentCount,
        public readonly int $maxAllowed,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    public static function limitReached(int $controlExecutionId, int $currentCount, int $maxAllowed): self
    {
        return new self(
            technicalMessage: sprintf(
                'ControlExecution %d already has %d documents (max %d).',
                $controlExecutionId,
                $currentCount,
                $maxAllowed,
            ),
            userMessage: sprintf(
                'Limite de %d documents par contrôle atteinte. '
                .'Supprimez un document existant avant d\'en ajouter un nouveau.',
                $maxAllowed,
            ),
            controlExecutionId: $controlExecutionId,
            currentCount: $currentCount,
            maxAllowed: $maxAllowed,
        );
    }
}
