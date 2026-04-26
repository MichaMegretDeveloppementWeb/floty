<?php

namespace App\Data\User\Planning;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\VehicleUserType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une ligne de la heatmap Planning — un véhicule sur 52 semaines avec
 * sa densité d'utilisation et son agrégat fiscal annuel.
 */
#[TypeScript]
final class PlanningHeatmapVehicleData extends Data
{
    /**
     * @param  list<int>  $weeks  52 entiers (0-7) — densité jours utilisés / semaine
     */
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public VehicleUserType $userType,
        public EnergySource $energy,
        public HomologationMethod $co2Method,
        public ?int $co2Value,
        public ?int $taxableHorsepower,
        public array $weeks,
        public int $daysTotal,
        public float $annualTaxDue,
    ) {}
}
