<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Data\User\Control\ControlRecipientData;
use App\Enums\Control\RecipientLevel;
use App\Enums\Control\RecipientOperation;
use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderSettings;

final class ControlReminderSettingsReadRepository implements ControlReminderSettingsReadRepositoryInterface
{
    public function get(): ControlReminderSettings
    {
        return ControlReminderSettings::singleton();
    }

    /**
     * @return array<int, ControlRecipientData>
     */
    public function defaultRecipients(): array
    {
        return ControlRecipientDelta::query()
            ->where('level', RecipientLevel::Settings)
            ->where('operation', RecipientOperation::Include)
            ->orderBy('id')
            ->get()
            ->map(static fn (ControlRecipientDelta $delta): ControlRecipientData => new ControlRecipientData(
                name: (string) $delta->name,
                email: $delta->email,
            ))
            ->all();
    }
}
