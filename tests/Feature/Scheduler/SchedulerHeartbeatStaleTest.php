<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatReadRepositoryInterface;
use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatWriteRepositoryInterface;
use App\Models\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du heartbeat planificateur (Chantier B / B3).
 */
final class SchedulerHeartbeatStaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_heartbeat_recent_n_est_pas_perime(): void
    {
        $now = CarbonImmutable::parse('2026-06-05 12:00:00');
        app(SchedulerHeartbeatWriteRepositoryInterface::class)->touch($now);

        $reader = app(SchedulerHeartbeatReadRepositoryInterface::class);

        self::assertFalse($reader->isStale($now->addMinutes(30), 180));
    }

    #[Test]
    public function un_heartbeat_ancien_est_perime(): void
    {
        app(SchedulerHeartbeatWriteRepositoryInterface::class)->touch(CarbonImmutable::parse('2026-06-05 06:00:00'));

        $reader = app(SchedulerHeartbeatReadRepositoryInterface::class);

        self::assertTrue($reader->isStale(CarbonImmutable::parse('2026-06-05 12:00:00'), 180));
    }

    #[Test]
    public function un_heartbeat_absent_est_perime(): void
    {
        SchedulerHeartbeat::singleton();
        SchedulerHeartbeat::query()->where('id', 1)->update(['last_run_at' => null]);

        $reader = app(SchedulerHeartbeatReadRepositoryInterface::class);

        self::assertTrue($reader->isStale(CarbonImmutable::now(), 180));
    }
}
