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
 * `rentalPriceFullYear` est un **placeholder V1.2** (module facturation
 * non encore implémenté, cf. roadmap_v12_facturation). Toujours `null`
 * en V1, exposé dès maintenant pour que la colonne UI puisse afficher
 * « — » plutôt que d'apparaître plus tard et déstabiliser la table.
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
        public float $fullYearTax,
        public float $dailyTaxRate,
        public ?float $rentalPriceFullYear,
    ) {}
}
