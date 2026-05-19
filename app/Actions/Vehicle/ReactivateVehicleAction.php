<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Models\Vehicle;

/**
 * Reactivates a vehicle previously marked as exited: clears
 * `exit_date` and `exit_reason`, and sets `current_status` to `Active`
 * (ADR-0018 § 8.2).
 *
 * Single UPDATE, no transaction, no cross-table pre-condition: a
 * reactivated vehicle is free to receive new contracts and
 * unavailabilities without constraint.
 */
final readonly class ReactivateVehicleAction
{
    public function __construct(
        private VehicleWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $vehicleId): Vehicle
    {
        return $this->writer->markAsActive($vehicleId);
    }
}
