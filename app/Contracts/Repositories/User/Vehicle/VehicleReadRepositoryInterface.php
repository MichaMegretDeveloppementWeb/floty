<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Models\Vehicle;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * @param  bool  $includeExited  Si false (défaut), exclut les véhicules
     *                               dont `exit_date` est antérieure ou égale
     *                               à aujourd'hui (cf. ADR-0018 § 4 - Index
     *                               Flotte par défaut "aujourd'hui").
     * @return Collection<int, Vehicle>
     *
     * @deprecated Conservé temporairement — sera retiré en L6 du chantier
     *             ADR-0020. Utiliser {@see paginateForIndex()}.
     */
    public function findAllForFleetView(bool $includeExited = false): Collection;

    /**
     * Liste paginée server-side de l'Index Vehicles (cf. ADR-0020).
     * Applique `{search, includeExited, status, sortKey, sortDirection,
     * page, perPage}` du DTO en SQL pur.
     *
     * Search : LIKE sur `license_plate OR brand OR model`.
     * Sort whitelist : licensePlate | model | firstFrenchRegistrationDate
     * | acquisitionDate | currentStatus.
     *
     * Eager-load des `fiscalCharacteristics` actives pour éviter N+1 sur
     * le calcul de `fullYearTax` côté service.
     *
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginateForIndex(VehicleIndexQueryData $query): LengthAwarePaginator;

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
     * Liste des véhicules pour la heatmap planning d'une **année donnée** :
     * inclut tous les véhicules actifs au moins une partie de l'année
     * (cf. scope {@see Vehicle::scopeActiveAt} avec `start_of_year`),
     * eager-loading des caractéristiques fiscales actives, triés par
     * plaque.
     *
     * Cf. ADR-0018 § 4 - un véhicule sorti mi-année reste affiché dans
     * la heatmap de l'année où il était partiellement actif (cellules
     * postérieures à exit_date grisées côté frontend).
     *
     * @return Collection<int, Vehicle>
     */
    public function findAllForHeatmap(int $year): Collection;

    /**
     * Compte les véhicules actifs (sans `exit_date`).
     */
    public function countActive(): int;

    /**
     * Bornes min/max des années de 1ʳᵉ immatriculation française parmi
     * tous les véhicules. Utilisé par le filtre Index pour borner le
     * sélecteur d'année. Retourne `null` si la table est vide.
     *
     * @return array{min: int, max: int}|null
     */
    public function findFirstRegistrationYearBounds(): ?array;
}
