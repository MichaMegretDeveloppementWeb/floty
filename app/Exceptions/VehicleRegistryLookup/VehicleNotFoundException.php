<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Exceptions\BaseAppException;

/**
 * Le provider a répondu mais la plaque n'existe pas dans son registre.
 * Cas fonctionnel attendu · l'utilisateur doit pouvoir saisir le
 * véhicule manuellement sans alerte d'erreur technique.
 */
final class VehicleNotFoundException extends BaseAppException
{
    public static function forPlate(string $plate): self
    {
        return new self(
            technicalMessage: "License plate [{$plate}] not found in the registry provider.",
            userMessage: "Plaque [{$plate}] introuvable. Saisissez les informations du véhicule manuellement.",
        );
    }
}
