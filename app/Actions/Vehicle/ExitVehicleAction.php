<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Exceptions\Vehicle\VehicleExitBlockedByConflictsException;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleExitImpactComputer;
use Illuminate\Support\Facades\DB;

/**
 * Records the exit of a vehicle from the fleet (sale, destruction,
 * unresolved theft, transfer, other definitive reason).
 *
 * Pipeline (transaction):
 *   1. Compute conflicts via {@see VehicleExitImpactComputer}.
 *   2. If any contract or unavailability spills past the exit date,
 *      raise {@see VehicleExitBlockedByConflictsException} (no silent
 *      magic, ADR-0018 D7).
 *   3. Otherwise persist `exit_date`, `exit_reason` and update
 *      `current_status`.
 */
final readonly class ExitVehicleAction
{
    public function __construct(
        private VehicleWriteRepositoryInterface $writer,
        private VehicleExitImpactComputer $impactComputer,
    ) {}

    public function execute(int $vehicleId, ExitVehicleData $data): Vehicle
    {
        return DB::transaction(function () use ($vehicleId, $data): Vehicle {
            $impact = $this->impactComputer->computeImpact($vehicleId, $data->exitDate);

            if ($impact->hasConflicts) {
                throw VehicleExitBlockedByConflictsException::withImpact($impact);
            }

            return $this->writer->markAsExited($vehicleId, $data);
        });
    }
}
