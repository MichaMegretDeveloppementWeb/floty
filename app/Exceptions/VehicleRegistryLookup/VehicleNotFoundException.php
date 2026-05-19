<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Exceptions\BaseAppException;

final class VehicleNotFoundException extends BaseAppException
{
    /**
     * Build the exception for an unknown license plate.
     */
    public static function forPlate(string $plate): self
    {
        return new self(
            technicalMessage: "License plate [{$plate}] not found in the registry provider.",
            userMessage: "Plaque [{$plate}] introuvable. Saisissez les informations du véhicule manuellement.",
        );
    }
}
