<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine Vehicle.
 *
 * Toutes les requêtes Eloquent non triviales (eager-loading conditionnel,
 * filtres, agrégations) qui ciblent {@see Vehicle} vivent ici (R3-bis +
 * R4 d'ADR-0013). Les services consomment ces méthodes pour orchestrer
 * la logique métier sans toucher à `Vehicle::query()`.
 */
interface VehicleReadRepositoryInterface
{
    /**
     * Liste de tous les véhicules pour la page « Flotte », triés par
     * `acquisition_date DESC` avec eager-loading des caractéristiques
     * fiscales actives (`effective_to IS NULL`).
     *
     * @return Collection<int, Vehicle>
     */
    public function findAllForFleetView(): Collection;

    /**
     * Liste des véhicules disponibles (non sortis) pour les `<SelectInput>`,
     * colonnes minimales, triés par plaque.
     *
     * @return Collection<int, Vehicle>
     */
    public function findAllForOptions(): Collection;

    /**
     * Précharge en bulk un ensemble de véhicules par ids avec eager-loading
     * des caractéristiques fiscales actives, indexés par id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Vehicle>
     */
    public function findByIdsIndexed(array $ids): Collection;

    /**
     * Lookup unitaire avec eager-loading des caractéristiques fiscales
     * actives, échoue si l'id n'existe pas.
     */
    public function findOrFailWithFiscal(int $id): Vehicle;

    /**
     * Lookup unitaire avec eager-loading de **toutes** les versions
     * fiscales du véhicule (historique complet, ordonné `effective_from
     * DESC`). Échoue avec 404 si l'id n'existe pas.
     *
     * Utilisé par la page Show pour composer `VehicleData` avec à la
     * fois la version courante et la timeline historique.
     */
    public function findByIdWithFiscalHistory(int $id): Vehicle;

    /**
     * Liste des véhicules pour la heatmap planning : actifs (non
     * supprimés), eager-loading des caractéristiques fiscales actives,
     * triés par plaque.
     *
     * @return Collection<int, Vehicle>
     */
    public function findAllForHeatmap(): Collection;

    /**
     * Compte les véhicules actifs (sans `exit_date`).
     */
    public function countActive(): int;
}
