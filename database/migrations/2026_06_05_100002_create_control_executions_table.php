<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B2 · execution history of a control on a vehicle ("Fait").
 *
 * Each execution records the date (a plain DATE: retroactive entry allowed, the
 * client may log it a few days after actually doing it), an optional note, who
 * recorded it, and a link to the generated vehicle event (Chantier A). The most
 * recent non-deleted execution becomes the new reference for the next échéance.
 * Soft-deleted (legal history); never hard-deleted.
 *
 * An execution targets either a global definition (possibly overridden) or a
 * vehicle-specific override, never both (CHECK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_executions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->foreignId('control_definition_id')
                ->nullable()
                ->constrained('control_definitions')
                ->restrictOnDelete();

            $table->foreignId('vehicle_control_override_id')
                ->nullable()
                ->constrained('vehicle_control_overrides')
                ->restrictOnDelete();

            $table->foreignId('vehicle_event_id')
                ->nullable()
                ->constrained('vehicle_events')
                ->restrictOnDelete();

            $table->date('executed_on');
            $table->string('note', 500)->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'executed_on']);
            $table->index('deleted_at');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Exactly one control target identity is set.
        DB::statement(<<<'SQL'
            ALTER TABLE `control_executions`
                ADD CONSTRAINT `chk_ce_target`
                CHECK (
                    (control_definition_id IS NOT NULL AND vehicle_control_override_id IS NULL)
                    OR (control_definition_id IS NULL AND vehicle_control_override_id IS NOT NULL)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('control_executions');
    }
};
