<?php

declare(strict_types=1);

namespace App\DTO\Planning;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\VehicleUserType;

/**
 * One vehicle row of the planning PDF export render context.
 *
 * Internal transport (Service → Renderer → Blade), not exposed to the
 * front. All amounts are recomputed server-side; `weeks` holds the real
 * usage days (0-7) per ISO week of the year (company-scoped in the
 * per-company view).
 */
final readonly class PlanningExportRowData
{
    /**
     * @param  array<int, int>  $weeks  53 cells, real usage days (0-7)
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
        public PollutantCategory $pollutantCategory,
        public string $firstFrenchRegistrationDate,
        public array $weeks,
        public int $daysTotal,
        public float $fullYearTax,
        public float $annualTaxDue,
        public ?int $dailyRateCents,
        public ?int $weeklyRateCents,
        public ?int $monthlyRateCents,
    ) {}
}
