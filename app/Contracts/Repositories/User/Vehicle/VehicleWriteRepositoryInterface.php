<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Models\Vehicle;

/**
 * Écritures sur l'entité Vehicle stricto sensu.
 *
 * Ne porte plus la transaction « Vehicle + caractéristiques fiscales »
 * — l'orchestration multi-entités est désormais à la charge de
 * {@see CreateVehicleAction} (ADR-0013 R3 :
 * tout enchaînement de plusieurs services/repositories autour d'une
 * décision métier appartient à la couche Action).
 */
interface VehicleWriteRepositoryInterface
{
    /**
     * Persiste un véhicule sans toucher à son historique fiscal.
     */
    public function create(StoreVehicleData $data): Vehicle;
}
