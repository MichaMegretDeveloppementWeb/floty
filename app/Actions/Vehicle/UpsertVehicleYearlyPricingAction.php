<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\VehicleYearlyPricing;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the pricing of a vehicle for a given year.
 * Idempotent (UNIQUE(vehicle_id, year) + `updateOrCreate`). Wrapped
 * in a transaction for consistency with the rest of the Vehicle
 * domain.
 */
final readonly class UpsertVehicleYearlyPricingAction
{
    public function __construct(
        private VehicleYearlyPricingWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $vehicleId, VehicleYearlyPricingData $data): VehicleYearlyPricing
    {
        return DB::transaction(fn (): VehicleYearlyPricing => $this->writer->upsert($vehicleId, $data));
    }
}
