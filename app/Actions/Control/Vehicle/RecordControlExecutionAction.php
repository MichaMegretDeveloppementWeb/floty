<?php

declare(strict_types=1);

namespace App\Actions\Control\Vehicle;

use App\Actions\VehicleEvent\CreateVehicleEventAction;
use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Data\User\Control\Vehicle\RecordControlExecutionData;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\ControlExecution;
use Illuminate\Support\Facades\DB;

/**
 * Records a control execution ("Fait", Chantier B / B2). In one transaction it
 * generates the vehicle event (Chantier A, type Other / category "Contrôle",
 * non-fiscal, unavailability per the EFFECTIVE definition flag) and persists the
 * execution row linked to that event. The recorded date becomes the new
 * reference for the next échéance. Documents are uploaded separately by the
 * controller (mirroring the VehicleEvent create flow).
 *
 * A "Fait" is a point-in-time act, so the generated event is a SINGLE day
 * (start = end = execution date). A null end_date would mean "ongoing", which
 * for an `implies_unavailability` control would mark the vehicle unavailable
 * indefinitely in the heatmap / usage / planning views.
 */
final readonly class RecordControlExecutionAction
{
    public function __construct(
        private ControlDefinitionReadRepositoryInterface $definitions,
        private VehicleControlOverrideReadRepositoryInterface $overrides,
        private ControlExecutionWriteRepositoryInterface $executions,
        private CreateVehicleEventAction $createEvent,
    ) {}

    public function execute(RecordControlExecutionData $data, int $userId): ControlExecution
    {
        return DB::transaction(function () use ($data, $userId): ControlExecution {
            [$name, $impliesUnavailability] = $this->resolveTarget($data);

            $event = $this->createEvent->execute(new StoreVehicleEventData(
                vehicleId: $data->vehicleId,
                type: VehicleEventType::Other,
                startDate: $data->executedOn,
                endDate: $data->executedOn,
                description: $data->note,
                title: $name,
                category: 'Contrôle',
                impliesUnavailability: $impliesUnavailability,
            ));

            return $this->executions->create([
                'vehicle_id' => $data->vehicleId,
                'control_definition_id' => $data->controlDefinitionId,
                'vehicle_control_override_id' => $data->vehicleControlOverrideId,
                'vehicle_event_id' => $event->id,
                'executed_on' => $data->executedOn,
                'note' => $data->note,
                'recorded_by' => $userId,
            ]);
        });
    }

    /**
     * Effective control name + unavailability flag for the targeted control.
     *
     * @return array{0: string, 1: bool}
     */
    private function resolveTarget(RecordControlExecutionData $data): array
    {
        if ($data->controlDefinitionId !== null) {
            $definition = $this->definitions->findById($data->controlDefinitionId);
            $override = $this->overrides->findForVehicle($data->vehicleId)
                ->firstWhere('control_definition_id', $data->controlDefinitionId);

            $name = $override?->name ?? $definition?->name ?? 'Contrôle';
            $implies = $override?->implies_unavailability ?? $definition?->implies_unavailability ?? false;

            return [$name, (bool) $implies];
        }

        $override = $this->overrides->findById((int) $data->vehicleControlOverrideId);

        return [
            (string) ($override?->name ?? 'Contrôle'),
            (bool) ($override?->implies_unavailability ?? false),
        ];
    }
}
