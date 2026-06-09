<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Materialised cache column for the fleet "control due" filter: earliest
     * date from which the vehicle has at least one active regulatory control
     * needing attention (= MIN of `next_due - reminder_window` over its active
     * controls, excluding controls falling on or after a planned exit). NULL =
     * no active control / never due. Derived value: the live computation
     * (ControlScheduleService via FleetControlScheduleScanner) stays the source
     * of truth; this column is recomputed on writes + nightly + drift-checked.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->date('controls_due_from')->nullable()->after('exit_reason');
            $table->index('controls_due_from', 'vehicles_controls_due_from_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropIndex('vehicles_controls_due_from_idx');
            $table->dropColumn('controls_due_from');
        });
    }
};
