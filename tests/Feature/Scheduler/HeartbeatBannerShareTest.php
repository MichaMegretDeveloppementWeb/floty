<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatWriteRepositoryInterface;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du partage Inertia du drapeau heartbeat (Chantier B / B3) qui pilote le
 * bandeau « Planificateur inactif ».
 */
final class HeartbeatBannerShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function un_heartbeat_recent_n_active_pas_le_bandeau(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/controls')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('schedulerHeartbeatStale', false));
    }

    #[Test]
    public function un_heartbeat_perime_active_le_bandeau(): void
    {
        $user = User::factory()->create();
        app(SchedulerHeartbeatWriteRepositoryInterface::class)->touch(CarbonImmutable::now()->subHours(5));

        $this->actingAs($user)
            ->get('/app/controls')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('schedulerHeartbeatStale', true));
    }
}
