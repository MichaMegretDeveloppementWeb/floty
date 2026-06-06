<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier A · lifecycle events. `system_kind` flags an event as
 * system-generated (fleet exit, acquisition) rather than user-created:
 *   - it is read-only (no manual edit/delete) ;
 *   - it lets the exit be cleanly removed when the exit is cancelled
 *     (reactivation), and the acquisition event re-synced on date change.
 * NULL = ordinary user event. Additive, nullable · safe on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('system_kind', 20)->nullable()->after('type');
            $table->index(['vehicle_id', 'system_kind']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropIndex(['vehicle_id', 'system_kind']);
            $table->dropColumn('system_kind');
        });
    }
};
