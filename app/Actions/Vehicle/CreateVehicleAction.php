<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un véhicule + sa première période de caractéristiques
 * fiscales (`change_reason = InitialCreation`, `effective_to = null`).
 *
 * **Décision métier** : tout véhicule nouvellement créé doit être
 * accompagné d'une période fiscale initiale dont le `effective_from`
 * coïncide avec sa date d'acquisition. C'est cette règle qui justifie
 * d'orchestrer deux écritures atomiquement dans une Action plutôt que
 * de la cacher dans le repository (ADR-0013 R3 : multi-entités → Action).
 *
 * Les invariants de cohérence (homologation ↔ CO₂, énergie ↔ moteur,
 * etc.) sont validés en amont par le FormRequest et — à terme — par
 * un `VehicleFiscalCharacteristicsService` (phase 04 complète).
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
