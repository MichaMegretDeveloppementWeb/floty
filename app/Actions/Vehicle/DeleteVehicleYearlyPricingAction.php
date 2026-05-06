<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Exceptions\Vehicle\VehicleYearlyPricingNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Supprime le tarif d'un véhicule pour une année donnée. Lance
 * {@see VehicleYearlyPricingNotFoundException} si rien à supprimer.
 *
 * Wrappé dans `DB::transaction` pour cohérence avec le reste des Actions
 * du domaine Vehicle.
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
