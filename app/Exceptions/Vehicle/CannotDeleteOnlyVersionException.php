<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * Tentative de suppression de l'unique VFC d'un véhicule. Un véhicule
 * doit toujours avoir au moins une période fiscale active - supprimer
 * la dernière reviendrait à laisser le moteur fiscal sans données.
 *
 * Pour réinitialiser complètement la fiscalité d'un véhicule, l'opérateur
 * doit passer par le formulaire d'édition (mode « Correction ») plutôt
 * que de supprimer l'unique VFC.
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
