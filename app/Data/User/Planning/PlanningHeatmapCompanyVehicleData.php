<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\VehicleUserType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the company-scoped heatmap: a vehicle across 52 weeks with
 * both global density (cell colour) and company-scoped density (cell
 * number).
 *
 * Differs from {@see PlanningHeatmapVehicleData}:
 *   - `weeksGlobal` (all companies) drives the cell colour, signaling
 *     vehicle availability.
 *   - `weeksForCompany` (selected company) drives the cell number,
 *     showing days used by that company specifically.
 *   - `daysTotalForCompany` aggregates only the company's share.
 *
 * The 3 fiscal fields (`annualTaxDueForCompany`, `fullYearTax`,
 * `dailyTaxRate`) live on the deferred {@see PlanningHeatmapVehicleRealCostsData}
 * / {@see PlanningHeatmapVehicleFullYearCostsData}. The frontend cost
 * map is keyed by `vehicleId` and `annualTaxDue` already reflects the
 * company scope.
 */
#[TypeScript]
final class PlanningHeatmapCompanyVehicleData extends Data
{
    /**
     * @param  list<int>  $weeksGlobal  52 ints (0-7); global density across all companies (cell colour).
     * @param  list<int>  $weeksForCompany  52 ints (0-7); density for the selected company (cell number).
     * @param  list<int>  $weeksWithVehicleEvent  ISO week numbers (1-52) carrying at least one unavailability day.
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
        public array $weeksGlobal,
        public array $weeksForCompany,
        public int $daysTotalForCompany,
        public ?string $exitDate,
        public array $weeksWithVehicleEvent,
        public ?int $dailyRateCents,
        public ?int $weeklyRateCents,
        public ?int $monthlyRateCents,
    ) {}
}
