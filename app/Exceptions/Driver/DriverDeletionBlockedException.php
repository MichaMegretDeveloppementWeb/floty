<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

/**
 * Suppression d'un driver refusée car il a des contrats associés
 * (passés, actifs ou futurs). Cohérence historique préservée.
 */
final class DriverDeletionBlockedException extends BaseAppException
{
    public static function hasContracts(int $driverId, int $contractsCount): self
    {
        return new self(
            sprintf('Driver %d cannot be deleted: %d contracts associated.', $driverId, $contractsCount),
            sprintf(
                'Impossible de supprimer ce conducteur : %d location(s) lui sont associées. '
                .'La suppression complète n\'est possible que si aucune location ne référence le conducteur.',
                $contractsCount,
            ),
        );
    }
}
