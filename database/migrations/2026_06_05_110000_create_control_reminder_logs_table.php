<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B3 · idempotence journal for control reminder emails. One row per
 * fired occurrence so the daily cron never double-sends, even run twice a day.
 *
 * `target_key` ('def:{id}' or 'ovr:{id}') is the non-null dedup key: the FK
 * columns are nullable (a control targets either a global definition or a
 * vehicle-specific override, CHECK), and MySQL treats NULLs as distinct in a
 * unique index, so a unique on the nullable FKs would NOT enforce uniqueness
 * for global controls (override id always NULL). The non-null `target_key`
 * fixes that. FK columns are kept for referential integrity (RESTRICT).
 * Append-only (no soft-delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_reminder_logs', function (Blueprint $table): void {
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

            $table->string('target_key', 24);
            $table->date('due_on');
            $table->date('reminder_on');
            $table->string('kind', 16);
            $table->unsignedSmallInteger('recipients_count');
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(['vehicle_id', 'target_key', 'due_on', 'reminder_on'], 'uniq_crl_occurrence');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `control_reminder_logs`
                ADD CONSTRAINT `chk_crl_target`
                CHECK (
                    (control_definition_id IS NOT NULL AND vehicle_control_override_id IS NULL)
                    OR (control_definition_id IS NULL AND vehicle_control_override_id IS NOT NULL)
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_reminder_logs`
                ADD CONSTRAINT `chk_crl_kind`
                CHECK (kind IN ('before', 'on_due', 'after'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('control_reminder_logs');
    }
};
