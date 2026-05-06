<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\VehicleYearlyPricing;

/**
 * Écritures sur les tarifs jour/semaine/mois d'un véhicule par année.
 *
 * Cf. ADR-0013 (couches strictes Controller→Action→Service→Repository).
 *
 * **Idempotence** : la méthode {@see self::upsert()} repose sur la
 * contrainte UNIQUE(vehicle_id, year) en base + `updateOrCreate`. Appeler
 * deux fois avec les mêmes paramètres produit le même résultat.
 */
interface VehicleYearlyPricingWriteRepositoryInterface
{
    /**
     * Crée ou met à jour le tarif d'un véhicule pour une année donnée.
     * Idempotent grâce à la contrainte UNIQUE(vehicle_id, year).
     */
    public function upsert(int $vehicleId, VehicleYearlyPricingData $data): VehicleYearlyPricing;

    /**
     * Supprime le tarif d'un véhicule pour une année donnée. Retourne
     * `true` si une ligne a été supprimée, `false` si aucun tarif
     * n'existait.
     */
    public function deleteForVehicleAndYear(int $vehicleId, int $year): bool;
}
