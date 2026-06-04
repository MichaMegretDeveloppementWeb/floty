<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\VehicleUserType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the planning heatmap: a vehicle across 52 weeks with its
 * usage density and total days used.
 *
 * The fiscal fields live on the deferred companion DTOs
 * ({@see PlanningHeatmapVehicleFullYearCostsData} and
 * {@see PlanningHeatmapVehicleRealCostsData}) so the grid renders
 * immediately on mount.
 */
#[TypeScript]
final class PlanningHeatmapVehicleData extends Data
{
    /**
     * @param  list<int>  $weeks  52 ints (0-7); used days per week.
     * @param  list<int>  $weeksWithVehicleEvent  ISO week numbers (1-52) carrying at least one unavailability day; feeds the red border (ADR-0019 § 2 D5).
     * @param  ?int  $dailyRateCents  Daily rate for the current year (`vehicle_yearly_pricings`); null when no rate is set.
     * @param  ?int  $weeklyRateCents  Weekly rate; null when absent.
     * @param  ?int  $monthlyRateCents  Monthly rate; null when absent.
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
        public array $weeksWithVehicleEvent,
        public ?int $dailyRateCents,
        public ?int $weeklyRateCents,
        public ?int $monthlyRateCents,
    ) {}
}
