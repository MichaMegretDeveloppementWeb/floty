<?php

declare(strict_types=1);

namespace App\Repositories\User\Heartbeat;

use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatWriteRepositoryInterface;
use App\Models\SchedulerHeartbeat;
use Carbon\CarbonImmutable;

final class SchedulerHeartbeatWriteRepository implements SchedulerHeartbeatWriteRepositoryInterface
{
    public function touch(CarbonImmutable $at): void
    {
        $heartbeat = SchedulerHeartbeat::singleton();
        $heartbeat->last_run_at = $at;
        $heartbeat->save();
    }
}
