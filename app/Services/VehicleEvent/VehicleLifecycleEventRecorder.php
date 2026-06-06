<?php

declare(strict_types=1);

namespace App\Services\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\Vehicle;

/**
 * Records vehicle lifecycle markers in the events timeline (Chantier A):
 * acquisition (vehicle creation) and fleet exit. These are system-generated,
 * single-day, category "Cycle de vie", non-fiscal and NOT marked as
 * unavailability (the vehicle's post-exit absence is already handled by
 * `exit_date` everywhere, ADR-0018 · the event is a carnet-de-bord marker).
 * They are read-only client + server side ({@see VehicleEventSystemKind}).
 *
 * Each `record*` first removes the existing marker of that kind for the
 * vehicle, so changing the acquisition/exit date or re-exiting after a
 * cancellation stays idempotent.
 */
final readonly class VehicleLifecycleEventRecorder
{
    private const string CATEGORY = 'Cycle de vie';

    public function __construct(
        private VehicleEventWriteRepositoryInterface $events,
        private VehicleEventReadRepositoryInterface $eventsRead,
    ) {}

    /**
     * Records (or re-syncs) the "Entrée en flotte" marker on the acquisition
     * date. The cost is preserved across a re-sync: when called without an
     * explicit `$amountCents` (e.g. an acquisition-date correction), the cost
     * of the existing marker is kept rather than wiped by the delete + recreate.
     */
    public function recordAcquisition(Vehicle $vehicle, ?int $amountCents = null): void
    {
        // Preserve the existing cost on a re-sync. The `??` short-circuits, so
        // the lookup only hits the DB when NO amount is provided (an
        // acquisition-date correction), never on a create that already supplies
        // one · no wasted query on the common path.
        $finalAmountCents = $amountCents
            ?? $this->eventsRead->findSystemEventForVehicle(
                $vehicle->id,
                VehicleEventSystemKind::Acquisition,
            )?->amount_cents;

        $this->events->deleteSystemEventsForVehicle($vehicle->id, VehicleEventSystemKind::Acquisition);

        $date = $vehicle->acquisition_date->toDateString();

        $this->events->create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleEventType::Other,
            'system_kind' => VehicleEventSystemKind::Acquisition,
            'title' => VehicleEventSystemKind::Acquisition->title(),
            'has_fiscal_impact' => false,
            'implies_unavailability' => false,
            'start_date' => $date,
            'end_date' => $date,
            'description' => null,
            'amount_cents' => $finalAmountCents,
        ], [self::CATEGORY]);
    }

    public function recordExit(Vehicle $vehicle, ExitVehicleData $data): void
    {
        $this->events->deleteSystemEventsForVehicle($vehicle->id, VehicleEventSystemKind::FleetExit);

        $note = $data->note !== null ? trim($data->note) : '';
        $description = $note !== ''
            ? $data->exitReason->label().' · '.$note
            : $data->exitReason->label();

        $this->events->create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleEventType::Other,
            'system_kind' => VehicleEventSystemKind::FleetExit,
            'title' => VehicleEventSystemKind::FleetExit->title(),
            'has_fiscal_impact' => false,
            'implies_unavailability' => false,
            'start_date' => $data->exitDate,
            'end_date' => $data->exitDate,
            'description' => $description,
            'amount_cents' => $data->amountCents,
        ], [self::CATEGORY]);
    }

    public function removeExit(int $vehicleId): void
    {
        $this->events->deleteSystemEventsForVehicle($vehicleId, VehicleEventSystemKind::FleetExit);
    }
}
