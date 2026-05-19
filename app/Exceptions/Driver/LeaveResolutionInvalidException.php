<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

/**
 * The leave-resolution proposal is inconsistent with the detected contracts
 * (e.g. `replace` mode without a full `replacementMap`, or a replacement driver
 * inactive on the contract period).
 */
final class LeaveResolutionInvalidException extends BaseAppException
{
    public static function missingReplacement(int $contractId): self
    {
        return new self(
            sprintf('Replacement driver missing for contract %d.', $contractId),
            sprintf(
                'Aucun conducteur de remplacement n\'a été choisi pour la location #%d. '
                .'Choisissez un conducteur ou optez pour le détachement complet.',
                $contractId,
            ),
        );
    }

    public static function replacementDriverInvalid(int $contractId, int $driverId): self
    {
        return new self(
            sprintf('Replacement driver %d invalid for contract %d period or company.', $driverId, $contractId),
            sprintf(
                'Le conducteur de remplacement choisi pour la location #%d n\'est pas '
                .'actif dans la bonne entreprise sur la période de la location. Choisissez un autre conducteur.',
                $contractId,
            ),
        );
    }
}
