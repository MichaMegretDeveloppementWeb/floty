<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `controls_due_from`: materialised cache for the "control due" fleet
     * filter (earliest date a vehicle's active control needs attention; NULL if
     * none). Maintained by ControlDueDateRecomputeService.
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
