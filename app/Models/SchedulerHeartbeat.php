<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Scheduler liveness heartbeat (Chantier B / B3). Application singleton (id=1),
 * its `last_run_at` bumped by an hourly scheduled task; a stale value surfaces a
 * UI warning that the cron may be dead (ADR-0008). Same race-safe singleton
 * pattern as {@see FiscalRiskSettings::singleton()}.
 *
 * @property int $id
 * @property CarbonImmutable|null $last_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'last_run_at',
])]
final class SchedulerHeartbeat extends Model
{
    protected $table = 'scheduler_heartbeats';

    /**
     * Returns the singleton row, auto-creating it with `last_run_at = now()` so a
     * fresh deployment is not instantly reported as stale.
     */
    public static function singleton(): self
    {
        return self::unguarded(fn (): self => self::query()->firstOrCreate(['id' => 1], [
            'last_run_at' => now(),
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_run_at' => 'immutable_datetime',
        ];
    }
}
