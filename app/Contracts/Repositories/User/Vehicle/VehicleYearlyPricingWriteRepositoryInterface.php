<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\VehicleYearlyPricing;

/**
 * Writes on per-year vehicle day/week/month rates.
 *
 * Idempotency: {@see self::upsert()} relies on the UNIQUE(vehicle_id,
 * year) database constraint combined with `updateOrCreate`. Calling
 * twice with the same parameters produces the same result.
 */
interface VehicleYearlyPricingWriteRepositoryInterface
{
    /**
     * Creates or updates the rate of a vehicle for a given year.
     * Idempotent thanks to the UNIQUE(vehicle_id, year) constraint.
     */
    public function upsert(int $vehicleId, VehicleYearlyPricingData $data): VehicleYearlyPricing;

    /**
     * Deletes the rate of a vehicle for a given year. Returns true if
     * a row was deleted, false if no rate existed.
     */
    public function deleteForVehicleAndYear(int $vehicleId, int $year): bool;
}
