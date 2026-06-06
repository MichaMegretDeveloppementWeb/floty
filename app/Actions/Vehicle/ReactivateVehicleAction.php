<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Models\Vehicle;
use App\Services\VehicleEvent\VehicleLifecycleEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Reactivates a vehicle previously marked as exited: clears `exit_date` and
 * `exit_reason`, sets `current_status` to `Active` (ADR-0018 § 8.2), and
 * removes the « Sortie de flotte » carnet-de-bord marker (the exit is
 * cancelled). The two writes are coordinated in one transaction.
 */
final readonly class ReactivateVehicleAction
{
    public function __construct(
        private VehicleWriteRepositoryInterface $writer,
        private VehicleLifecycleEventRecorder $lifecycle,
    ) {}

    public function execute(int $vehicleId): Vehicle
    {
        return DB::transaction(function () use ($vehicleId): Vehicle {
            $vehicle = $this->writer->markAsActive($vehicleId);

            $this->lifecycle->removeExit($vehicleId);

            return $vehicle;
        });
    }
}
