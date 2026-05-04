<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Models\VehicleFiscalCharacteristics;
use DateTimeInterface;

/**
 * Écritures sur l'historique fiscal d'un véhicule.
 *
 * Séparé de {@see VehicleWriteRepositoryInterface} : le cycle de vie
 * d'une période fiscale (création initiale, nouvelle version, correction
 * d'une version existante, cascade rétroactive) est piloté par les
 * Actions du domaine (cf. ADR-0013 R3 - orchestration multi-entités
 * appartient à la couche Action, pas au repository).
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

    /**
     * Crée une nouvelle ligne d'historique avec les caractéristiques
     * fournies. Utilisé par l'Action `UpdateVehicleAction` après
     * fermeture/suppression des versions adjacentes.
     */
    public function createNewVersion(
        int $vehicleId,
        UpdateVehicleData $data,
        DateTimeInterface $effectiveFrom,
        FiscalCharacteristicsChangeReason $reason,
        ?string $note,
    ): VehicleFiscalCharacteristics;

    /**
     * Met à jour la borne `effective_to` d'une version existante.
     * Utilisé pour clôturer une version courante quand on en crée
     * une nouvelle, ou pour restaurer `null` quand la version
     * courante est supprimée.
     */
    public function setEffectiveTo(
        int $fiscalId,
        ?DateTimeInterface $effectiveTo,
    ): VehicleFiscalCharacteristics;

    /**
     * Supprime physiquement (HARD DELETE) toutes les versions VFC
     * d'un véhicule dont `effective_from` est postérieure ou égale à
     * la date donnée. Utilisé par la cascade rétroactive : quand on
     * crée une nouvelle version avec `effective_from` dans le passé,
     * toutes les versions postérieures n'ont plus de sens et sont
     * effacées.
     *
     * @return int Nombre de lignes supprimées
     */
    public function deleteVersionsFromDate(
        int $vehicleId,
        DateTimeInterface $date,
    ): int;

    /**
     * Met à jour la borne `effective_from` d'une version existante.
     * Utilisé pour ajuster la VFC suivante quand sa précédente est
     * supprimée et que l'utilisateur a choisi `ExtendNext`.
     */
    public function setEffectiveFrom(
        int $fiscalId,
        DateTimeInterface $effectiveFrom,
    ): VehicleFiscalCharacteristics;

    /**
     * UPDATE complet (bornes + champs fiscaux + motif/note) d'une VFC
     * historique depuis la modale Historique. Les invariants
     * inter-versions sont validés en amont par l'Action.
     */
    public function updateBoundsAndFields(
        int $fiscalId,
        UpdateFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics;

    /**
     * Supprime physiquement (HARD DELETE) une VFC unique. L'éventuel
     * comblement du trou laissé est à la charge de l'Action appelante.
     */
    public function deleteOne(int $fiscalId): void;
}
