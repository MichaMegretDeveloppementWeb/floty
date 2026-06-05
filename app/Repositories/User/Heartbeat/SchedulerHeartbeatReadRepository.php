<?php

declare(strict_types=1);

namespace App\Repositories\User\Heartbeat;

use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatReadRepositoryInterface;
use App\Models\SchedulerHeartbeat;
use Carbon\CarbonImmutable;

final class SchedulerHeartbeatReadRepository implements SchedulerHeartbeatReadRepositoryInterface
{
    public function lastRunAt(): ?CarbonImmutable
    {
        return SchedulerHeartbeat::singleton()->last_run_at?->toImmutable();
    }

    public function isStale(CarbonImmutable $now, int $thresholdMinutes): bool
    {
        $lastRunAt = $this->lastRunAt();

        return $lastRunAt === null
            || $lastRunAt->lessThan($now->subMinutes($thresholdMinutes));
    }
}
