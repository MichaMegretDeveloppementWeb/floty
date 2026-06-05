<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Heartbeat;

use Carbon\CarbonImmutable;

/**
 * Reads of the scheduler heartbeat (Chantier B / B3).
 */
interface SchedulerHeartbeatReadRepositoryInterface
{
    public function lastRunAt(): ?CarbonImmutable;

    /**
     * Stale when the heartbeat is missing or older than `$thresholdMinutes`.
     */
    public function isStale(CarbonImmutable $now, int $thresholdMinutes): bool;
}
