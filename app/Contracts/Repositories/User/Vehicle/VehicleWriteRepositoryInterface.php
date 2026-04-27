<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\StoreVehicleData;
use App\Models\Vehicle;

/**
 * Écritures sur le domaine Vehicle.
 *
 * Encapsule la création transactionnelle d'un véhicule + sa première
 * période de caractéristiques fiscales (R4 d'ADR-0013 : repositories
 * possèdent les transactions liées à leurs entités).
 */
interface VehicleWriteRepositoryInterface
{
    /**
     * Crée le véhicule + ses caractéristiques fiscales initiales dans
     * une transaction atomique. Retourne l'instance créée.
     */
    public function createWithInitialFiscalCharacteristics(StoreVehicleData $data): Vehicle;
}
