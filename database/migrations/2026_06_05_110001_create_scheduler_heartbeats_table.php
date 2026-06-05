<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B3 · scheduler heartbeat (application singleton id=1). An hourly
 * scheduled task updates `last_run_at`; the UI raises a warning banner when it
 * grows stale, detecting a dead cron within hours (ADR-0008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_heartbeats');
    }
};
