<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

/**
 * Workflow Q6 : la résolution proposée par l'utilisateur n'est pas
 * cohérente avec les contrats détectés (par exemple, mode 'replace'
 * sans `replacementMap` complet, ou driver de remplacement non actif
 * sur la période du contrat).
 */
final class LeaveResolutionInvalidException extends BaseAppException
{
    public static function missingReplacement(int $contractId): self
    {
        return new self(
            sprintf('Replacement driver missing for contract %d.', $contractId),
            sprintf(
                'Aucun conducteur de remplacement n\'a été choisi pour le contrat #%d. '
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
                'Le conducteur de remplacement choisi pour le contrat #%d n\'est pas '
                .'actif dans la bonne entreprise sur la période du contrat. Choisissez un autre conducteur.',
                $contractId,
            ),
        );
    }
}
