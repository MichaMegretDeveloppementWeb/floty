<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Listener registration relies on Laravel's auto-discovery, which scans
 * `app/Listeners` and registers each listener from the type-hint of its
 * `handle()` method. No manual mapping is needed as long as listeners
 * respect the convention; this provider exists for future cases that
 * cannot be auto-discovered (subscribers, custom registration).
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap event listeners (handled by auto-discovery).
     */
    public function boot(): void {}
}
