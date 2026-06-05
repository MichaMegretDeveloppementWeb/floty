<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Heartbeat;

use Carbon\CarbonImmutable;

/**
 * Writes of the scheduler heartbeat (Chantier B / B3).
 */
interface SchedulerHeartbeatWriteRepositoryInterface
{
    /**
     * Bump the heartbeat to the given instant (called hourly by the scheduler).
     */
    public function touch(CarbonImmutable $at): void;
}
