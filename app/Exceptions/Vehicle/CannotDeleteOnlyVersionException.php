<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * Cannot delete the only fiscal version of a vehicle. A vehicle must always retain at least one active VFC.
 */
final class CannotDeleteOnlyVersionException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'Cannot delete the only fiscal version of a vehicle.',
            userMessage: 'Impossible de supprimer cette version : c\'est la seule de l\'historique. Un véhicule doit toujours avoir au moins une période fiscale active. Utilisez plutôt « Correction de la version courante » pour rectifier les valeurs.',
        );
    }
}
