<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;

/**
 * Lectures sur l'historique des caractéristiques fiscales d'un véhicule.
 *
 * Utilisé par le moteur fiscal pour résoudre la période active à un
 * instant donné. Séparé de {@see VehicleReadRepositoryInterface} car le
 * moteur fiscal ne dépend pas du Vehicle dans son ensemble — uniquement
 * de la période fiscale courante.
 */
interface VehicleFiscalCharacteristicsReadRepositoryInterface
{
    /**
     * Caractéristiques fiscales courantes (`effective_to IS NULL`,
     * dernière en date par `effective_from`) d'un véhicule donné.
     * Renvoie `null` si le véhicule n'a aucune période active.
     */
    public function findCurrentForVehicle(Vehicle $vehicle): ?VehicleFiscalCharacteristics;
}
