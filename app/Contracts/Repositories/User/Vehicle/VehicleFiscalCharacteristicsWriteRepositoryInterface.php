<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\StoreVehicleData;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

/**
 * Écritures sur l'historique fiscal d'un véhicule.
 *
 * Séparé de {@see VehicleWriteRepositoryInterface} : le cycle de vie
 * d'une période fiscale (création initiale, nouvelle version, correction
 * d'une version existante) est piloté par les Actions du domaine
 * (cf. ADR-0013 R3 — orchestration multi-entités appartient à la
 * couche Action, pas au repository).
 */
interface VehicleFiscalCharacteristicsWriteRepositoryInterface
{
    /**
     * Crée la première période fiscale d'un véhicule fraîchement
     * inséré (`change_reason = InitialCreation`, `effective_to = null`).
     *
     * Les invariants métier (homologation, cohérence WLTP/CO₂, etc.)
     * sont supposés validés en amont (FormRequest + Action).
     */
    public function createInitialVersion(
        int $vehicleId,
        StoreVehicleData $data,
        DateTimeInterface $effectiveFrom,
    ): VehicleFiscalCharacteristics;
}
