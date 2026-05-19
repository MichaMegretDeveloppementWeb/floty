<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Creates a vehicle alongside its first fiscal characteristics period
 * (`change_reason = InitialCreation`, `effective_to = null`).
 *
 * Business rule: every newly created vehicle is paired with an initial
 * fiscal period whose `effective_from` matches the acquisition date.
 * The two writes are coordinated atomically here rather than hidden in
 * the repository (ADR-0013 R3: multi-entity write goes through an
 * Action).
 */
final readonly class CreateVehicleAction
{
    public function __construct(
        private VehicleWriteRepositoryInterface $vehicles,
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $fiscalCharacteristics,
    ) {}

    public function execute(StoreVehicleData $data): Vehicle
    {
        return DB::transaction(function () use ($data): Vehicle {
            $vehicle = $this->vehicles->create($data);

            $this->fiscalCharacteristics->createInitialVersion(
                vehicleId: $vehicle->id,
                data: $data,
                effectiveFrom: $vehicle->acquisition_date,
            );

            return $vehicle;
        });
    }
}
