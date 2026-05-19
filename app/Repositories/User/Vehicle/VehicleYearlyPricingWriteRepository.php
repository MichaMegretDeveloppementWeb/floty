<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\VehicleYearlyPricing;

/**
 * Eloquent implementation of vehicle × year rate writes.
 *
 * Stateless repository (singleton via
 * {@see App\Providers\RepositoryServiceProvider}).
 */
final class VehicleYearlyPricingWriteRepository implements VehicleYearlyPricingWriteRepositoryInterface
{
    public function upsert(int $vehicleId, VehicleYearlyPricingData $data): VehicleYearlyPricing
    {
        // updateOrCreate relies on the UNIQUE(vehicle_id, year)
        // constraint for idempotency: calling twice with the same
        // parameters produces the same row.
        return VehicleYearlyPricing::updateOrCreate(
            [
                'vehicle_id' => $vehicleId,
                'year' => $data->year,
            ],
            [
                'daily_rate_cents' => $data->dailyRateCents,
                'weekly_rate_cents' => $data->weeklyRateCents,
                'monthly_rate_cents' => $data->monthlyRateCents,
            ],
        );
    }

    public function deleteForVehicleAndYear(int $vehicleId, int $year): bool
    {
        $deleted = VehicleYearlyPricing::query()
            ->where('vehicle_id', $vehicleId)
            ->where('year', $year)
            ->delete();

        return $deleted > 0;
    }
}
