<?php

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\VehicleStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la table « Flotte » (page User/Vehicles/Index).
 *
 * Inclut les agrégats fiscaux annuels — calculés côté
 * `VehicleService::buildListItems()` (à créer en phase 2).
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
        public float $annualTaxDue,
    ) {}
}
