<?php

declare(strict_types=1);

namespace App\Actions\Control\Vehicle;

use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideWriteRepositoryInterface;
use App\Data\User\Control\Vehicle\SetVehicleControlStatusData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Quick status toggle for a control on a vehicle (Chantier B / B2): pause,
 * disable or reactivate. For a GLOBAL control it upserts the (sparse) override
 * row carrying just the status; for a vehicle-specific control it updates the
 * existing override row.
 */
final readonly class SetVehicleControlStatusAction
{
    public function __construct(
        private VehicleControlOverrideReadRepositoryInterface $reader,
        private VehicleControlOverrideWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $vehicleId, SetVehicleControlStatusData $data): void
    {
        if ($data->controlDefinitionId !== null) {
            $this->writer->upsertForVehicleDefinition($vehicleId, $data->controlDefinitionId, [
                'status' => $data->status,
            ]);

            return;
        }

        $override = $this->reader->findById((int) $data->vehicleControlOverrideId);

        if ($override === null || $override->vehicle_id !== $vehicleId) {
            throw new NotFoundHttpException('Contrôle introuvable pour ce véhicule.');
        }

        $this->writer->update($override, ['status' => $data->status]);
    }
}
