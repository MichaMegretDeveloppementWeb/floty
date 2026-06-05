<?php

declare(strict_types=1);

namespace App\Actions\Control\Vehicle;

use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideWriteRepositoryInterface;
use App\Data\User\Control\Vehicle\VehicleControlOverrideFormData;
use App\Enums\Control\VehicleControlStatus;
use App\Models\ControlDefinition;
use App\Models\ControlReminderSettings;
use App\Models\VehicleControlOverride;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates or updates a per-vehicle control (Chantier B) with its level-2
 * recipient deltas, in one transaction. Two kinds:
 *   - override of a GLOBAL definition: each field is persisted only if it DIFFERS
 *     from the global value (else NULL = keeps inheriting); the name is never
 *     overridden. A fully inert override (nothing differs, default recipients,
 *     active) is not materialised, and any existing one is reset (soft-deleted) ;
 *   - vehicle-SPECIFIC control: the complete recipe is stored as-is.
 */
final readonly class SaveVehicleControlOverrideAction
{
    public function __construct(
        private VehicleControlOverrideWriteRepositoryInterface $repository,
        private ControlDefinitionReadRepositoryInterface $definitions,
        private ControlReminderSettingsReadRepositoryInterface $reminderSettings,
    ) {}

    public function execute(
        VehicleControlOverrideFormData $data,
        int $vehicleId,
        ?VehicleControlOverride $existing = null,
    ): ?VehicleControlOverride {
        return DB::transaction(function () use ($data, $vehicleId, $existing): ?VehicleControlOverride {
            $isSpecific = $data->controlDefinitionId === null;

            $definition = $isSpecific
                ? null
                : $this->definitions->findById((int) $data->controlDefinitionId);

            if (! $isSpecific && $definition === null) {
                throw new NotFoundHttpException('Contrôle global introuvable.');
            }

            $settings = $this->reminderSettings->get();
            $attributes = $this->buildAttributes($data, $isSpecific, $definition, $settings);

            // A fully inert override (no field differs from the global, default
            // recipients, active) means "pure global default": do not materialise
            // a row, and reset any existing one.
            if (! $isSpecific
                && $attributes['status'] === VehicleControlStatus::Active
                && $this->isInert($attributes, $data)
            ) {
                if ($existing !== null) {
                    $this->repository->softDelete($existing);
                }

                return null;
            }

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
    private function buildAttributes(
        VehicleControlOverrideFormData $data,
        bool $isSpecific,
        ?ControlDefinition $definition,
        ControlReminderSettings $settings,
    ): array {
        $attributes = [
            'control_definition_id' => $data->controlDefinitionId,
            'status' => $data->status,
        ];

        if ($isSpecific) {
            // Vehicle-specific control: the complete recipe is its own.
            $attributes['name'] = $data->name;
            $attributes['anchor'] = $data->anchor;
            $attributes['initial_duration_value'] = $data->initialDurationValue;
            $attributes['initial_duration_unit'] = $data->initialDurationUnit;
            $attributes['cycle_value'] = $data->cycleValue;
            $attributes['cycle_unit'] = $data->cycleUnit;
            $attributes['notify_driver'] = $data->notifyDriver;
            $attributes['implies_unavailability'] = $data->impliesUnavailability;
        } else {
            // Override: store a field only when it differs from the global (else
            // NULL = keep inheriting). The name is never overridden.
            $attributes['name'] = null;
            $attributes['anchor'] = $data->anchor === $definition->anchor ? null : $data->anchor;
            $attributes['initial_duration_value'] = $data->initialDurationValue === $definition->initial_duration_value ? null : $data->initialDurationValue;
            $attributes['initial_duration_unit'] = $data->initialDurationUnit === $definition->initial_duration_unit ? null : $data->initialDurationUnit;
            $attributes['cycle_value'] = $data->cycleValue === $definition->cycle_value ? null : $data->cycleValue;
            $attributes['cycle_unit'] = $data->cycleUnit === $definition->cycle_unit ? null : $data->cycleUnit;
            $attributes['notify_driver'] = $data->notifyDriver === $definition->notify_driver ? null : $data->notifyDriver;
            $attributes['implies_unavailability'] = $data->impliesUnavailability === $definition->implies_unavailability ? null : $data->impliesUnavailability;
        }

        // Reminders: store only what differs from the inherited baseline
        // (specific = general settings; override = definition then settings).
        if ($data->customizeReminders) {
            $baselineDays = $isSpecific ? $settings->days_before : ($definition->reminder_days_before ?? $settings->days_before);
            $baselineOnDue = $isSpecific ? $settings->remind_on_due_day : ($definition->reminder_on_due_day ?? $settings->remind_on_due_day);
            $baselineRepeat = $isSpecific ? $settings->repeat_every_days : ($definition->reminder_repeat_every_days ?? $settings->repeat_every_days);

            $attributes['reminder_days_before'] = $data->reminderDaysBefore === $baselineDays ? null : $data->reminderDaysBefore;
            $attributes['reminder_on_due_day'] = $data->reminderOnDueDay === $baselineOnDue ? null : $data->reminderOnDueDay;
            $attributes['reminder_repeat_every_days'] = $data->reminderRepeatEveryDays === $baselineRepeat ? null : $data->reminderRepeatEveryDays;
        } else {
            $attributes['reminder_days_before'] = null;
            $attributes['reminder_on_due_day'] = null;
            $attributes['reminder_repeat_every_days'] = null;
        }

        return $attributes;
    }

    /**
     * An override row that overrides nothing and carries no recipient delta.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function isInert(array $attributes, VehicleControlOverrideFormData $data): bool
    {
        $overridable = [
            'name', 'anchor', 'initial_duration_value', 'initial_duration_unit',
            'cycle_value', 'cycle_unit', 'notify_driver', 'implies_unavailability',
            'reminder_days_before', 'reminder_on_due_day', 'reminder_repeat_every_days',
        ];

        foreach ($overridable as $key) {
            if ($attributes[$key] !== null) {
                return false;
            }
        }

        return $data->ownRecipients === [] && $data->excludedDefaultEmails === [];
    }
}
