<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Models\VehicleYearlyPricing;

/**
 * Lectures sur les tarifs jour/semaine/mois d'un véhicule par année.
 *
 * Cf. ADR-0013 (couches strictes Controller→Action→Service→Repository).
 *
 * Consommé par :
 *   - `VehicleYearlyPricing[Read|Write]Repository` (cohérence applicative)
 *   - `BillingCalculator` (chantier 14.C) pour récupérer les tarifs à
 *     appliquer lors d'un calcul de facture
 *   - Future couche de présentation `VehicleData` (chantier 14.B) pour
 *     exposer les tarifs existants à l'UI Vehicle Show
 */
interface VehicleYearlyPricingReadRepositoryInterface
{
    /**
     * Tarif d'un véhicule pour une année donnée. Retourne `null` si
     * aucun tarif n'a été défini pour cette année.
     */
    public function findForVehicleAndYear(int $vehicleId, int $year): ?VehicleYearlyPricing;

    /**
     * Tous les tarifs définis pour un véhicule, triés par année
     * croissante. Liste vide si aucun tarif n'a encore été défini.
     *
     * @return list<VehicleYearlyPricing>
     */
    public function findAllForVehicle(int $vehicleId): array;

    /**
     * Variante batched pour usage Index/Listings (Phase 13 D5.10.L) ·
     * récupère les tarifs de plusieurs véhicules pour une année en
     * une seule SQL. Évite les N+1 sur les pages paginées.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, VehicleYearlyPricing> vehicleId → pricing (clé manquante si pas de tarif)
     */
    public function findForVehiclesAndYear(array $vehicleIds, int $year): array;
}
