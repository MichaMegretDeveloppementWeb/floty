<?php

declare(strict_types=1);

namespace App\Notifications\Control;

use App\Enums\Control\ReminderKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Regulatory control reminder email (Chantier B / B3). Sent on-demand to an
 * arbitrary recipient (`Notification::route('mail', $email)`), synchronous (no
 * ShouldQueue, no queue in V1). Constructor takes scalars only for safe
 * serialization; the body sentence is precomputed per {@see ReminderKind}.
 */
final class ControlReminderNotification extends Notification
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $controlName,
        private readonly string $licensePlate,
        private readonly string $dueDate,
        private readonly ReminderKind $kind,
        private readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(sprintf('Rappel contrôle · %s · %s', $this->controlName, $this->licensePlate))
            ->view('emails.control-reminder', [
                'recipientName' => $this->recipientName,
                'controlName' => $this->controlName,
                'licensePlate' => $this->licensePlate,
                'kindLabel' => $this->kind->label(),
                'bodySentence' => $this->bodySentence(),
                'url' => $this->url,
            ]);
    }

    /**
     * French body sentence nuanced by the reminder kind.
     */
    private function bodySentence(): string
    {
        $due = CarbonImmutable::parse($this->dueDate)->format('d/m/Y');

        return match ($this->kind) {
            ReminderKind::Before => sprintf(
                'Le contrôle « %s » du véhicule %s arrive à échéance le %s.',
                $this->controlName,
                $this->licensePlate,
                $due,
            ),
            ReminderKind::OnDue => sprintf(
                'Le contrôle « %s » du véhicule %s arrive à échéance aujourd\'hui (%s).',
                $this->controlName,
                $this->licensePlate,
                $due,
            ),
            ReminderKind::After => sprintf(
                'Le contrôle « %s » du véhicule %s est en retard depuis le %s.',
                $this->controlName,
                $this->licensePlate,
                $due,
            ),
        };
    }
}
