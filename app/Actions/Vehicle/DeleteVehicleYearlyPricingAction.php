<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Exceptions\Vehicle\VehicleYearlyPricingNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the pricing of a vehicle for a given year. Raises
 * {@see VehicleYearlyPricingNotFoundException} when nothing exists.
 */
final readonly class DeleteVehicleYearlyPricingAction
{
    public function __construct(
        private VehicleYearlyPricingWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $vehicleId, int $year): void
    {
        DB::transaction(function () use ($vehicleId, $year): void {
            $deleted = $this->writer->deleteForVehicleAndYear($vehicleId, $year);

            if (! $deleted) {
                throw VehicleYearlyPricingNotFoundException::forVehicleAndYear($vehicleId, $year);
            }
        });
    }
}
