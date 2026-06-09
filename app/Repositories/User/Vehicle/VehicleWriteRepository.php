<?php

declare(strict_types=1);

namespace App\Repositories\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Data\User\Vehicle\StoreVehicleData;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Vehicle writes.
 *
 * Does not carry the "Vehicle + fiscal characteristics" transaction ·
 * that is the role of {@see CreateVehicleAction} which orchestrates
 * both repositories under a `DB::transaction`.
 */
final class VehicleWriteRepository implements VehicleWriteRepositoryInterface
{
    public function create(StoreVehicleData $data): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $data->licensePlate,
            'brand' => $data->brand,
            'model' => $data->model,
            'vin' => $data->vin,
            'color' => $data->color,
            'first_french_registration_date' => $data->firstFrenchRegistrationDate,
            'first_origin_registration_date' => $data->firstOriginRegistrationDate,
            'first_economic_use_date' => $data->firstEconomicUseDate,
            'acquisition_date' => $data->acquisitionDate,
            'current_status' => VehicleStatus::Active,
            'mileage_current' => $data->mileageCurrent,
            'notes' => $data->notes,
        ]);
    }

    public function update(int $vehicleId, UpdateVehicleData $data): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $vehicle->update([
            'license_plate' => $data->licensePlate,
            'brand' => $data->brand,
            'model' => $data->model,
            'vin' => $data->vin,
            'color' => $data->color,
            'first_french_registration_date' => $data->firstFrenchRegistrationDate,
            'first_origin_registration_date' => $data->firstOriginRegistrationDate,
            'first_economic_use_date' => $data->firstEconomicUseDate,
            'acquisition_date' => $data->acquisitionDate,
            'mileage_current' => $data->mileageCurrent,
            'notes' => $data->notes,
        ]);

        return $vehicle->fresh();
    }

    public function markAsExited(int $vehicleId, ExitVehicleData $data): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $vehicle->update([
            'exit_date' => $data->exitDate,
            'exit_reason' => $data->exitReason,
            'current_status' => self::statusForExitReason($data->exitReason),
        ]);

        return $vehicle->fresh();
    }

    public function markAsActive(int $vehicleId): Vehicle
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $vehicle->update([
            'exit_date' => null,
            'exit_reason' => null,
            'current_status' => VehicleStatus::Active,
        ]);

        return $vehicle->fresh();
    }

    public function updateControlsDueFrom(array $dueFromByVehicleId): void
    {
        if ($dueFromByVehicleId === []) {
            return;
        }

        $ids = array_keys($dueFromByVehicleId);

        $caseFragments = [];
        $bindings = [];
        foreach ($dueFromByVehicleId as $vehicleId => $dueFrom) {
            $caseFragments[] = 'when ? then ?';
            $bindings[] = $vehicleId;
            $bindings[] = $dueFrom;
        }
        foreach ($ids as $vehicleId) {
            $bindings[] = $vehicleId;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        // Query-builder update (no model events) to avoid recursing into the
        // recompute observers.
        DB::update(
            sprintf(
                'update vehicles set controls_due_from = case id %s end where id in (%s)',
                implode(' ', $caseFragments),
                $placeholders,
            ),
            $bindings,
        );
    }

    /**
     * Maps an exit reason to the consistent `current_status`.
     * ADR-0018 § 3 · accepted asymmetry: Transferred and
     * StolenUnrecovered → Other.
     */
    private static function statusForExitReason(VehicleExitReason $reason): VehicleStatus
    {
        return match ($reason) {
            VehicleExitReason::Sold => VehicleStatus::Sold,
            VehicleExitReason::Destroyed => VehicleStatus::Destroyed,
            VehicleExitReason::Transferred,
            VehicleExitReason::StolenUnrecovered,
            VehicleExitReason::Other => VehicleStatus::Other,
        };
    }
}
