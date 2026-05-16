<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\VehicleUserType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une ligne de la heatmap Planning - un véhicule sur 52 semaines avec
 * sa densité d'utilisation et le total de jours utilisés.
 *
 * Les 3 champs fiscaux (`annualTaxDue`, `fullYearTax`, `dailyTaxRate`)
 * ont été extraits dans {@see PlanningHeatmapVehicleCostsData}, servi
 * en `Inertia::defer` car leur calcul coûte ~630 ms cold sur 64
 * véhicules. Le DTO heatmap reste eager pour rendre la grille immédiate
 * au mount.
 */
#[TypeScript]
final class PlanningHeatmapVehicleData extends Data
{
    /**
     * @param  list<int>  $weeks  52 entiers (0-7) - densité jours utilisés / semaine
     * @param  list<int>  $weeksWithUnavailability  numéros ISO (1-52) des
     *                                              semaines portant au moins
     *                                              un jour d'indispo (tous
     *                                              types confondus, peu
     *                                              importe que la semaine
     *                                              porte aussi un contrat).
     *                                              Alimente la bordure
     *                                              rouge des cellules
     *                                              heatmap (ADR-0019 § 2 D5).
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
        public ?string $exitDate,
        public array $weeksWithUnavailability,
    ) {}
}
