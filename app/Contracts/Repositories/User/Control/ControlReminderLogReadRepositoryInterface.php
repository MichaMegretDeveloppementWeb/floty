<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

/**
 * Reads of the reminder idempotence journal (Chantier B / B3).
 */
interface ControlReminderLogReadRepositoryInterface
{
    /**
     * Whether a reminder for this control occurrence (target + due date + send
     * date) was already journaled.
     */
    public function existsForOccurrence(
        int $vehicleId,
        ?int $definitionId,
        ?int $overrideId,
        string $dueOn,
        string $reminderOn,
    ): bool;
}
