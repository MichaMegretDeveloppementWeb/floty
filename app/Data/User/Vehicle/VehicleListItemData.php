<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the fleet table (User/Vehicles/Index).
 *
 * `fullYearTax`, `dailyTaxRate` and `rentalPriceFullYear` are nullable
 * because the SLIM listing serves them as null and the actual values
 * arrive via a deferred `Inertia::defer` fetch.
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
        /** Null until the deferred `vehiclesCosts` prop is hydrated. */
        public ?float $fullYearTax,
        /** Null until the deferred `vehiclesCosts` prop is hydrated. */
        public ?float $dailyTaxRate,
        /**
         * Null until deferred `vehiclesCosts` is hydrated OR when the vehicle
         * is missing an annual pricing.
         */
        public ?float $rentalPriceFullYear,
    ) {}
}
