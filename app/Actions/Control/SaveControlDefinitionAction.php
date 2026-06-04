<?php

declare(strict_types=1);

namespace App\Actions\Control;

use App\Contracts\Repositories\User\Control\ControlDefinitionWriteRepositoryInterface;
use App\Data\User\Control\ControlDefinitionFormData;
use App\Models\ControlDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a global control definition together with its level-1
 * recipient deltas, in a single transaction (definition + deltas are two
 * coordinated writes, ADR-0013 R3).
 *
 * Reminder override fields are stored only when the control opts into
 * customising its cycle; otherwise they are persisted as NULL so the control
 * inherits the global reminder settings.
 */
final readonly class SaveControlDefinitionAction
{
    public function __construct(
        private ControlDefinitionWriteRepositoryInterface $repository,
    ) {}

    public function execute(ControlDefinitionFormData $data, ?ControlDefinition $existing = null): ControlDefinition
    {
        return DB::transaction(function () use ($data, $existing): ControlDefinition {
            $attributes = [
                'name' => $data->name,
                'anchor' => $data->anchor,
                'initial_duration_value' => $data->initialDurationValue,
                'initial_duration_unit' => $data->initialDurationUnit,
                'cycle_value' => $data->cycleValue,
                'cycle_unit' => $data->cycleUnit,
                'notify_driver' => $data->notifyDriver,
                'implies_unavailability' => $data->impliesUnavailability,
                'is_active' => $data->isActive,
                'reminder_days_before' => $data->customizeReminders ? $data->reminderDaysBefore : null,
                'reminder_on_due_day' => $data->customizeReminders ? $data->reminderOnDueDay : null,
                'reminder_repeat_every_days' => $data->customizeReminders ? $data->reminderRepeatEveryDays : null,
            ];

            $definition = $existing === null
                ? $this->repository->create($attributes)
                : $this->repository->update($existing, $attributes);

            $this->repository->syncRecipients($definition, $data->ownRecipients, $data->excludedDefaultEmails);

            return $definition;
        });
    }
}
