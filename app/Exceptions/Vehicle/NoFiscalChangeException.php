<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * L'utilisateur a soumis le formulaire d'édition véhicule en mode
 * « Nouvelle version » sans avoir modifié aucune caractéristique
 * fiscale. Une nouvelle ligne d'historique sans changement n'a pas
 * de sens métier — l'UI doit normalement bloquer en amont (bouton
 * désactivé), cette exception est le filet de sécurité backend.
 */
final class NoFiscalChangeException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'New fiscal version requested but no fiscal field changed.',
            userMessage: 'Aucune caractéristique fiscale n\'a été modifiée. Sélectionnez « Correction de la version courante » ou modifiez au moins une valeur avant d\'enregistrer une nouvelle version.',
        );
    }
}
