<?php

declare(strict_types=1);

namespace App\Actions\Control\Vehicle;

use App\Contracts\Repositories\User\Control\VehicleControlOverrideWriteRepositoryInterface;
use App\Data\User\Control\Vehicle\VehicleControlOverrideFormData;
use App\Models\VehicleControlOverride;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a per-vehicle control (Chantier B / B2) together with its
 * level-2 recipient deltas, in one transaction. Handles both an override of a
 * GLOBAL definition (sparse: a section left un-customised is stored NULL so it
 * keeps inheriting the global) and a vehicle-SPECIFIC control (complete recipe).
 */
final readonly class SaveVehicleControlOverrideAction
{
    public function __construct(
        private VehicleControlOverrideWriteRepositoryInterface $repository,
    ) {}

    public function execute(
        VehicleControlOverrideFormData $data,
        int $vehicleId,
        ?VehicleControlOverride $existing = null,
    ): VehicleControlOverride {
        return DB::transaction(function () use ($data, $vehicleId, $existing): VehicleControlOverride {
            $isSpecific = $data->controlDefinitionId === null;
            $attributes = $this->buildAttributes($data, $isSpecific);

            if ($existing !== null) {
                $override = $this->repository->update($existing, $attributes);
            } elseif (! $isSpecific) {
                $override = $this->repository->upsertForVehicleDefinition($vehicleId, (int) $data->controlDefinitionId, $attributes);
            } else {
                $attributes['vehicle_id'] = $vehicleId;
                $override = $this->repository->create($attributes);
            }

            $this->repository->syncRecipients($override, $data->ownRecipients, $data->excludedDefaultEmails);

            return $override;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(VehicleControlOverrideFormData $data, bool $isSpecific): array
    {
        $attributes = [
            'control_definition_id' => $data->controlDefinitionId,
            'status' => $data->status,
        ];

        $customizeSchedule = $isSpecific || $data->customizeSchedule;
        $attributes['name'] = $customizeSchedule ? $data->name : null;
        $attributes['anchor'] = $customizeSchedule ? $data->anchor : null;
        $attributes['initial_duration_value'] = $customizeSchedule ? $data->initialDurationValue : null;
        $attributes['initial_duration_unit'] = $customizeSchedule ? $data->initialDurationUnit : null;
        $attributes['cycle_value'] = $customizeSchedule ? $data->cycleValue : null;
        $attributes['cycle_unit'] = $customizeSchedule ? $data->cycleUnit : null;

        $customizeBehaviour = $isSpecific || $data->customizeBehaviour;
        $attributes['notify_driver'] = $customizeBehaviour ? $data->notifyDriver : null;
        $attributes['implies_unavailability'] = $customizeBehaviour ? $data->impliesUnavailability : null;

        $attributes['reminder_days_before'] = $data->customizeReminders ? $data->reminderDaysBefore : null;
        $attributes['reminder_on_due_day'] = $data->customizeReminders ? $data->reminderOnDueDay : null;
        $attributes['reminder_repeat_every_days'] = $data->customizeReminders ? $data->reminderRepeatEveryDays : null;

        return $attributes;
    }
}
