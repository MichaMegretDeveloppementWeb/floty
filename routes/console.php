<?php

declare(strict_types=1);

use App\Contracts\Repositories\User\Heartbeat\SchedulerHeartbeatWriteRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (Chantier B / B3, ADR-0008)
|--------------------------------------------------------------------------
|
| Driven by a single cron line `* * * * * php85 <projet>/artisan schedule:run`
| (zero shell operators). All timing lives here; logging via Laravel Log.
*/

// Nightly self-heal of the materialised control due-date cache, then a drift
// check that logs a warning on any divergence (defence behind the write-time
// observers). Run before the reminder dispatch.
Schedule::command('controls:recompute-due-dates')
    ->dailyAt('06:30')
    ->timezone('Europe/Paris')
    ->withoutOverlapping();

Schedule::command('controls:verify-due-dates')
    ->dailyAt('06:45')
    ->timezone('Europe/Paris')
    ->withoutOverlapping();

// Daily control reminder dispatch (Europe/Paris).
Schedule::command('controls:dispatch-reminders')
    ->dailyAt('07:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping();

// Hourly liveness heartbeat: a stale value raises the "scheduler dead" UI alert.
Schedule::call(function (SchedulerHeartbeatWriteRepositoryInterface $heartbeat): void {
    $heartbeat->touch(CarbonImmutable::now());
})->hourly()->name('scheduler-heartbeat')->withoutOverlapping();
