<?php

declare(strict_types=1);

namespace App\Actions\Control\Reminder;

use App\Data\User\Control\ControlRecipientData;
use App\Data\User\Control\Vehicle\EffectiveControlData;
use App\Enums\Control\ReminderKind;
use App\Models\Vehicle;
use App\Notifications\Control\ControlReminderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Sends one control reminder email to one recipient (Chantier B / B3). Isolates
 * the mail facade from the dispatch orchestration. Synchronous (queue absent).
 */
final readonly class SendControlReminderAction
{
    public function send(
        ControlRecipientData $recipient,
        EffectiveControlData $control,
        Vehicle $vehicle,
        ReminderKind $kind,
    ): void {
        $url = route('user.vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'controls']);

        Notification::route('mail', $recipient->email)->notify(
            new ControlReminderNotification(
                recipientName: $recipient->name,
                controlName: $control->name,
                licensePlate: $vehicle->license_plate,
                dueDate: $control->nextDueDate,
                kind: $kind,
                url: $url,
            ),
        );
    }
}
