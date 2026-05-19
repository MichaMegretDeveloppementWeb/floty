<?php

declare(strict_types=1);

namespace App\Contracts\VehicleRegistryLookup;

use App\Data\User\Vehicle\VehicleRegistryLookupResultData;
use App\Enums\VehicleRegistryLookup\RegistryLookupProvider;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupRateLimitedException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupTimeoutException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;

/**
 * Strategy de lookup véhicule par plaque d'immatriculation.
 *
 * Résolution de l'implémentation active via
 * {@see App\Strategies\VehicleRegistryLookup\VehicleRegistryLookupStrategyFactory}.
 * Le binding par défaut (`VehicleRegistryLookupServiceProvider`) injecte
 * automatiquement le driver configuré dans `vehicle-registry.default`.
 *
 * Implémentations :
 *   - {@see App\Strategies\VehicleRegistryLookup\FakeVehicleRegistryLookupStrategy}
 *     (tests + dev local · interdite en production).
 *   - `AaaDataVehicleRegistryLookupStrategy` · à implémenter après
 *     signature contrat fournisseur (cf. workflow fournisseur dans
 *     `project-management/specifications-fonctionnelles/vehicle-registry-lookup/`).
 */
interface VehicleRegistryLookupInterface
{
    /**
     * Identité du provider qui sert la requête. Permet aux consommateurs
     * (controller, audit log, UI) de tracer la source sans connaître la
     * classe concrète.
     */
    public function provider(): RegistryLookupProvider;

    /**
     * Récupère les caractéristiques d'un véhicule à partir de sa plaque.
     *
     * @param  string  $licensePlate  plaque française · normalisation
     *                                de surface (espaces, tirets) à la
     *                                charge de la strategy si nécessaire
     *
     * @throws VehicleNotFoundException plaque inconnue côté provider
     * @throws RegistryLookupTimeoutException timeout réseau / provider
     * @throws RegistryLookupRateLimitedException quota provider dépassé
     * @throws RegistryLookupUnavailableException panne provider / config invalide
     */
    public function lookup(string $licensePlate): VehicleRegistryLookupResultData;
}
