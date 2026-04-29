<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

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

    /**
     * Dernière VFC d'un véhicule dont `effective_from` est strictement
     * antérieure à la date donnée. Utilisée pour ajuster la borne
     * `effective_to` de la version qui précède une nouvelle version
     * insérée à `$date`.
     */
    public function findLastVersionStrictlyBefore(
        int $vehicleId,
        DateTimeInterface $date,
    ): ?VehicleFiscalCharacteristics;

    /**
     * Lookup unitaire — échoue si l'id n'existe pas (404).
     */
    public function findById(int $id): VehicleFiscalCharacteristics;

    /**
     * VFC immédiatement adjacente (par ordre `effective_from`) à une
     * VFC donnée — soit la précédente (`direction = -1`), soit la
     * suivante (`direction = +1`). Utilisée pour combler les trous
     * créés par la modification des bornes ou la suppression.
     *
     * Renvoie `null` si la VFC fournie est respectivement la première
     * ou la dernière de l'historique.
     *
     * @param  -1|1  $direction
     */
    public function findAdjacent(
        VehicleFiscalCharacteristics $vfc,
        int $direction,
    ): ?VehicleFiscalCharacteristics;

    /**
     * Compte le nombre de VFC d'un véhicule. Utilisé par l'Action de
     * suppression pour bloquer la suppression de l'unique VFC.
     */
    public function countForVehicle(int $vehicleId): int;
}
