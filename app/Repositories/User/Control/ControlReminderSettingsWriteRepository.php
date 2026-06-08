<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlReminderSettingsWriteRepositoryInterface;
use App\Data\User\Control\ControlReminderSettingsData;
use App\Enums\Control\RecipientLevel;
use App\Enums\Control\RecipientOperation;
use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderSettings;
use App\Support\Control\RecipientEmail;
use Illuminate\Support\Facades\DB;

final class ControlReminderSettingsWriteRepository implements ControlReminderSettingsWriteRepositoryInterface
{
    public function update(ControlReminderSettingsData $data): ControlReminderSettings
    {
        return DB::transaction(function () use ($data): ControlReminderSettings {
            $settings = ControlReminderSettings::singleton();

            $settings->fill([
                'days_before' => $data->daysBefore,
                'remind_on_due_day' => $data->remindOnDueDay,
                'repeat_every_days' => $data->repeatEveryDays,
            ]);
            $settings->save();

            // Replace the whole level-0 recipient set: settings-level deltas are
            // the materialised default list, so a full resync stays idempotent.
            ControlRecipientDelta::query()
                ->where('level', RecipientLevel::Settings)
                ->delete();

            $seen = [];

            foreach ($data->defaultRecipients as $recipient) {
                $email = RecipientEmail::normalize($recipient->email);

                if (isset($seen[$email])) {
                    continue;
                }

                $seen[$email] = true;

                ControlRecipientDelta::query()->create([
                    'level' => RecipientLevel::Settings,
                    'control_definition_id' => null,
                    'vehicle_control_override_id' => null,
                    'operation' => RecipientOperation::Include,
                    'email' => $email,
                    'name' => $recipient->name,
                ]);
            }

            return $settings;
        });
    }
}
