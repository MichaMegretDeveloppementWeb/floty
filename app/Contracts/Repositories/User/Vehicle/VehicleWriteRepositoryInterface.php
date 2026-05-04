<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Models\Vehicle;

/**
 * Écritures sur l'entité Vehicle stricto sensu.
 *
 * Ne porte plus la transaction « Vehicle + caractéristiques fiscales »
 * - l'orchestration multi-entités est désormais à la charge de
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

    /**
     * Met à jour les seuls champs **identité** d'un véhicule
     * (license_plate, brand, model, vin, color, dates immat,
     * acquisition, kilométrage, notes). Ne touche pas à l'historique
     * fiscal - celui-ci est géré séparément par
     * {@see VehicleFiscalCharacteristicsWriteRepositoryInterface}
     * sous l'orchestration d'une Action.
     */
    public function update(int $vehicleId, UpdateVehicleData $data): Vehicle;

    /**
     * Marque un véhicule comme sorti de flotte : pose `exit_date`,
     * `exit_reason`, et met à jour `current_status` cohérent (mapping
     * sold→Sold, destroyed→Destroyed, autres motifs→Other ; cf. ADR-0018
     * § 3 - asymétrie acceptée pour minimiser le scope).
     *
     * La validation des conflits (contrats/indispos débordants) est à
     * la charge de l'Action appelante via {@see App\Services\Vehicle\VehicleExitImpactComputer}.
     */
    public function markAsExited(int $vehicleId, ExitVehicleData $data): Vehicle;

    /**
     * Réactive un véhicule précédemment sorti : reset `exit_date`,
     * `exit_reason` à NULL et `current_status` à Active.
     */
    public function markAsActive(int $vehicleId): Vehicle;
}
