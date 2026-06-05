<?php

declare(strict_types=1);

namespace App\Services\Control;

/**
 * Counters of one control-reminder dispatch run (Chantier B / B3), for logging
 * and the command console output.
 */
final readonly class ReminderRunSummary
{
    public function __construct(
        public int $vehiclesScanned,
        public int $controlsEvaluated,
        public int $remindersFired,
        public int $emailsSent,
        public int $skippedAlreadySent,
        public int $errors,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'vehicles_scanned' => $this->vehiclesScanned,
            'controls_evaluated' => $this->controlsEvaluated,
            'reminders_fired' => $this->remindersFired,
            'emails_sent' => $this->emailsSent,
            'skipped_already_sent' => $this->skippedAlreadySent,
            'errors' => $this->errors,
        ];
    }
}
