<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la table « Flotte » (page User/Vehicles/Index).
 *
 * Le coût présenté est **théorique** (`fullYearTax`) - ce que coûterait
 * le véhicule s'il était attribué 100 % du temps à une seule entreprise.
 * Permet de comparer les véhicules de la flotte indépendamment de leur
 * taux d'utilisation. La taxe réelle (compte tenu des attributions
 * existantes) est disponible sur la fiche détail Show.
 *
 * **Coûts en defer** (chantier perf Flotte 2026-05-17) ·
 * `fullYearTax`, `dailyTaxRate`, `rentalPriceFullYear` sont nullable ·
 * la liste SLIM ({@see VehicleListingService::listPaginatedSlim}) sert
 * le payload initial avec `null` sur ces 3 champs · les valeurs réelles
 * arrivent dans une 2e requête `Inertia::defer` côté
 * {@see VehicleController::index} qui appelle
 * {@see VehicleListingService::costsForVehicleIds}. Skeleton sur les
 * 2 colonnes le temps du fetch différé.
 */
#[TypeScript]
final class VehicleListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public VehicleStatus $currentStatus,
        public string $firstFrenchRegistrationDate,
        public string $acquisitionDate,
        public ?string $exitDate,
        public ?VehicleExitReason $exitReason,
        public bool $isExited,
        /** Null tant que la prop `vehiclesCosts` n'est pas hydratée (defer). */
        public ?float $fullYearTax,
        /** Null tant que la prop `vehiclesCosts` n'est pas hydratée (defer). */
        public ?float $dailyTaxRate,
        /**
         * Null tant que la prop `vehiclesCosts` n'est pas hydratée (defer),
         * OU si tarif annuel manquant pour ce véhicule.
         */
        public ?float $rentalPriceFullYear,
    ) {}
}
